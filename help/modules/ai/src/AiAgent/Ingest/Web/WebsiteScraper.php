<?php

namespace Ai\AiAgent\Ingest\Web;

use Ai\AiAgent\Ingest\Parsing\HtmlToMarkdown;
use Ai\AiAgent\Ingest\Parsing\MarkitdownParser;
use Ai\AiAgent\Ingest\Web\Exceptions\BlockedByFirewall;
use Ai\AiAgent\Ingest\Web\Exceptions\InvalidContentType;
use Exception;
use FileEye\MimeMap\Extension;
use FileEye\MimeMap\MappingException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;
use Throwable;

class WebsiteScraper
{
    const VALID_CONTENT_TYPES = [
        'text/html',
        'text/plain',
        'text/xml',
        'application/xhtml+xml',
        'application/xml',
        'application/json',
    ];

    public function scrape(string $url): ?array
    {
        if ($this->urlContentTypeIsInvalid($url)) {
            throw new InvalidContentType();
        }

        $host = parse_url($url, PHP_URL_HOST);
        $browser = new HttpBrowser();
        $html = null;

        try {
            $crawler = $browser->request(
                'GET',
                $url,
                server: [
                    'HTTP_USER_AGENT' =>
                        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                ],
            );

            // set host from final url, incase it redirected to a different host
            $finalUrl = $browser->getRequest()->getUri();
            $host = parse_url($finalUrl, PHP_URL_HOST);

            $html = $crawler->html();
        } catch (\Exception $e) {
            // if we can't handle content type of this page, throw InvalidContentType
            // exception to prevent retrying to scrape this page
            $contentType = $browser->getResponse()->getHeader('Content-Type');
            if (
                !$crawler->count() ||
                ($contentType &&
                    !in_array($contentType, self::VALID_CONTENT_TYPES))
            ) {
                throw new InvalidContentType();
            }
        }

        if (!$html) {
            return null;
        }

        if (
            str_contains($html, 'Cloudflare Ray ID') &&
            str_contains($html, 'blocked')
        ) {
            throw new BlockedByFirewall(
                'Cloudflare WAF blocked access to this website. Please add your server IP to the allowlist in your Cloudflare account.',
            );
        }

        $title = $crawler->filter('title')->text('');
        $language = $this->detectLanguage($crawler);

        return [
            'html' => $html,
            'title' => $title,
            'language' => $language,
            'host' => $host,
        ];
    }

    public function extractUrls(
        string $parentUrl,
        string $html,
        bool $nestedOnly = false,
    ): Collection {
        $parentUrl = rtrim($parentUrl, '/');
        return collect((new Crawler($html, $parentUrl))->filter('a')->links())
            ->map(function (Link $link) use ($parentUrl) {
                $url = $link->getUri();

                // convert relative URLs to absolute
                $parsedUrl = parse_url($url);
                if (!isset($parsedUrl['host'])) {
                    $baseHost = rtrim($parentUrl, '/');
                    $path = ltrim($parsedUrl['path'] ?? '', '/');
                    $url = $baseHost . '/' . $path;
                }

                // remove search and hash
                $url = strtok($url, '?#');

                // remove trailing slash
                $url = rtrim($url, '/');

                return $url;
            })
            ->filter(function ($url) use ($parentUrl, $nestedOnly) {
                $parsedHost = parse_url($url, PHP_URL_HOST);

                if ($parsedHost !== parse_url($parentUrl, PHP_URL_HOST)) {
                    return false;
                }

                if ($nestedOnly) {
                    return Str::of($url)->is("$parentUrl/*");
                }

                return true;
            })
            ->unique()
            ->take(100);
    }

    public function htmlToMarkdown(
        string $html,
        string $url,
        array|null $config = [],
    ): string|null {
        $host = parse_url($url, PHP_URL_HOST);

        $html = $this->tryToExtractOnlyRelevantContent($html, $config);

        if ($html === null) {
            return null;
        }

        if (!str_contains($html, '<body>')) {
            $html = '<html><body>' . $html . '</body></html>';
        }

        $markdown = null;

        if (config('app.ai_agent_document_parser') === 'markitdown') {
            try {
                $markdown = (new MarkitdownParser())->parseContent($html);
            } catch (Throwable $e) {
                report($e);
                $markdown = $this->basicHtmlToMarkdown($host, $html);
            }
        } else {
            $markdown = $this->basicHtmlToMarkdown($host, $html);
        }

        return $markdown;
    }

    protected function basicHtmlToMarkdown(string $host, string $html): string
    {
        $html = $this->purifyHtml($host, $html);
        $html = trim($this->convertToMarkdown($html));
        return $html;
    }

    protected function tryToExtractOnlyRelevantContent(
        string $html,
        array|null $config = [],
    ): string|null {
        $crawler = new Crawler($html);

        if (isset($config['contentCssSelector'])) {
            $crawler = $crawler->filter($config['contentCssSelector']);
        } else {
            $crawler = $crawler->filter('body');
        }

        $excludeSelector =
            'script, style, head, noscript, aside, template, svg, button, footer, nav, dialog, iframe, img';
        if (isset($config['cssSelectorsToExclude'])) {
            $excludeSelector .= ', ' . $config['cssSelectorsToExclude'];
        }

        $crawler->filter($excludeSelector)->each(function (Crawler $node) {
            foreach ($node as $n) {
                $n->parentNode->removeChild($n);
            }
        });

        // remove line numbers from code blocks
        $crawler->filter('pre .line-number')->each(function (Crawler $node) {
            foreach ($node as $n) {
                $n->parentNode->removeChild($n);
            }
        });

        try {
            return trim($crawler->html());
        } catch (Exception $e) {
            return null;
        }
    }

    protected function purifyHtml(string $host, string $html): string
    {
        $purifiedHtml = htmLawed($html, [
            'elements' =>
                'p,h1,h2,h3,h4,h5,h6,ul,ol,li,pre,code,table,thead,tbody,tr,td,th,a[href]',
            'any_custom_element' => 0,
            'base_url' => $host,
            'comment' => 1,
            'safe' => 1,
            'deny_attribute' => '* -href',
            'abs_url' => 1,
        ]);

        // remove empty tags
        $purifiedHtml = str_replace('<p></p>', '', $purifiedHtml);
        $purifiedHtml = str_replace('<code></code>', '', $purifiedHtml);
        $purifiedHtml = str_replace('<pre></pre>', '', $purifiedHtml);

        return $purifiedHtml;
    }

    protected function convertToMarkdown(string $html): string
    {
        return (new HtmlToMarkdown())->execute($html);
    }

    protected function detectLanguage(Crawler $crawler): ?string
    {
        // Check for language specified in the <html> tag's "lang" attribute
        $lang = $crawler->filter('html')->attr('lang', '');
        if ($lang) {
            return $lang;
        }

        // If not found, look for a meta tag with a specific language attribute
        $metaLang = $crawler
            ->filter('meta[http-equiv="content-language"]')
            ->attr('content', '');
        if ($metaLang) {
            return $metaLang;
        }

        // If still not found, check for a meta tag with "name=language"
        $namedLanguageMeta = $crawler
            ->filter('meta[name="language"]')
            ->attr('content', '');
        if ($namedLanguageMeta) {
            return $namedLanguageMeta;
        }

        return null;
    }

    protected function urlContentTypeIsInvalid(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!$extension) {
            return false;
        }

        try {
            $mimeType = (new Extension($extension))->getDefaultType();
            if (!in_array($mimeType, self::VALID_CONTENT_TYPES)) {
                return true;
            }
        } catch (MappingException) {
        }

        return false;
    }
}
