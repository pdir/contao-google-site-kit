<?php

declare(strict_types=1);

/*
 * This file is part of pdir/contao-site-kit.
 *
 * (c) pdir
 *
 * @license LGPL-3.0-or-later
 */

namespace Pdir\SiteKitBundle\EventListener;

use Contao\CoreBundle\Routing\ResponseContext\Csp\CspHandler;
use Contao\CoreBundle\Routing\ResponseContext\Csp\CspHandlerFactory;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\PageModel;
use Pdir\SiteKitBundle\Site\SiteRepository;
use Pdir\SiteKitBundle\Tracking\SnippetRenderer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Adds the Google tag / Tag Manager code to front end HTML responses. Works
 * independently of the page layout and template engine, so it behaves the same
 * in Contao 5 (HTML5 or Twig templates) and Contao 6.
 *
 * The listener runs before the HTTP cache stores the response, so cached pages
 * contain the code as well.
 */
#[AsEventListener(priority: -96)]
class TrackingSnippetListener
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly TokenChecker $tokenChecker,
        private readonly SiteRepository $sites,
        private readonly SnippetRenderer $renderer,
        private readonly CspHandlerFactory $cspHandlerFactory,
        private readonly bool $skipPreview = true,
        private readonly bool $skipBackendUsers = false,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$this->scopeMatcher->isFrontendMainRequest($event)) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$this->isHtmlPage($request, $response)) {
            return;
        }

        if ($this->skipPreview && $this->tokenChecker->isPreviewMode()) {
            return;
        }

        if ($this->skipBackendUsers && $this->tokenChecker->hasBackendUser()) {
            return;
        }

        $page = $request->attributes->get('pageModel');

        if (!$page instanceof PageModel || !$page->rootId) {
            return;
        }

        $site = $this->sites->find((int) $page->rootId);

        if (null === $site || !$this->renderer->isActive($site)) {
            return;
        }

        $content = (string) $response->getContent();

        // Do not add the code twice (e.g. if it is part of the layout already)
        if (str_contains($content, '<!-- Google Site Kit for Contao -->')) {
            return;
        }

        $headPos = stripos($content, '</head>');

        if (false === $headPos) {
            return;
        }

        $cspHandler = $this->createCspHandler($response);
        $nonce = $cspHandler?->getNonce('script-src');

        $content = substr($content, 0, $headPos).$this->renderer->renderHead($site, $nonce).substr($content, $headPos);

        if ('' !== ($body = $this->renderer->renderBody($site)) && preg_match('/<body\b[^>]*>/i', $content, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1] + \strlen($match[0][0]);
            $content = substr($content, 0, $offset)."\n".$body.substr($content, $offset);
        }

        $response->setContent($content);

        if (null !== $cspHandler) {
            foreach (SnippetRenderer::CSP_SOURCES as $directive => $sources) {
                $cspHandler->addSource($directive, $sources);
            }

            $cspHandler->applyHeaders($response, $request);
        }
    }

    private function isHtmlPage(Request $request, Response $response): bool
    {
        if ($request->isXmlHttpRequest() || !$response->isSuccessful() && !$response->isClientError()) {
            return false;
        }

        if ($response->headers->has('Content-Type') && !str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        return false === stripos((string) $response->headers->get('Content-Disposition'), 'attachment');
    }

    /**
     * Re-creates the CSP handler from the response header (the page's response
     * context is already finalized at this point). Returns null if no CSP is set.
     */
    private function createCspHandler(Response $response): CspHandler|null
    {
        if ($header = $response->headers->get('Content-Security-Policy-Report-Only')) {
            $reportOnly = true;
        } elseif ($header = $response->headers->get('Content-Security-Policy')) {
            $reportOnly = false;
        } else {
            return null;
        }

        $cspHandler = $this->cspHandlerFactory->create($header);
        $cspHandler->getDirectives()->setLevel1Fallback(false);
        $cspHandler->setReportOnly($reportOnly);

        return $cspHandler;
    }
}
