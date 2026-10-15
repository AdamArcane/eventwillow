<?php

namespace App\Console\Commands;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\URL;
use Throwable;

class ExportUserDocs extends Command
{
    protected $signature = 'docs:export-user
        {page? : User guide page key, for example getting-started}
        {--all : Export every user guide page}';

    protected $description = 'Export user guide pages as Markdown';

    private array $guidePaths = [];

    private array $warnings = [];

    private array $routeFallbacks = [];

    public function handle(Filesystem $files): int
    {
        $this->routeFallbacks = $this->buildRouteFallbacks($files);
        URL::resolveMissingNamedRoutesUsing(function (string $name, array $parameters = [], ?bool $absolute = true) {
            return $this->fallbackUrlForRoute($name, $absolute ?? true);
        });

        $pages = collect(config('docs.pages', []))
            ->filter(fn (array $page) => ($page['group'] ?? null) === 'user-guide')
            ->all();

        $pageKey = $this->argument('page');
        if ($this->option('all') === (bool) $pageKey) {
            $this->error('Choose either a page key or --all.');

            return self::FAILURE;
        }

        if (! extension_loaded('dom')) {
            $this->error('The PHP DOM extension is required to export documentation.');

            return self::FAILURE;
        }

        $selected = $this->option('all') ? $pages : [$pageKey => ($pages[$pageKey] ?? null)];
        if (! $this->option('all') && ! isset($selected[$pageKey])) {
            $this->error("Unknown user guide page '{$pageKey}'. Available pages: ".implode(', ', array_keys($pages)));

            return self::FAILURE;
        }

        $this->guidePaths = collect($pages)
            ->mapWithKeys(fn (array $page, string $key) => [$this->normalizePath($page['path']) => $key])
            ->all();

        $outputDirectory = base_path('userdocs');
        $files->ensureDirectoryExists($outputDirectory);

        foreach ($selected as $key => $page) {
            $this->warnings = [];

            try {
                $markdown = $this->exportPage($key, $page);
            } catch (Throwable $exception) {
                $this->error("Could not export {$key}: {$exception->getMessage()}");

                return self::FAILURE;
            }

            $destination = $outputDirectory.'/'.$key.'.md';
            $files->put($destination, $markdown);
            $this->info("Exported {$key} to userdocs/{$key}.md");

            foreach (array_unique($this->warnings) as $warning) {
                $this->warn("{$key}: {$warning}");
            }
        }

        return self::SUCCESS;
    }

    private function exportPage(string $key, array $page): string
    {
        $viewName = 'marketing.docs.'.str_replace('/', '.', $key);
        $html = view($viewName, ['customFieldsData' => []])->render();

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $content = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " doc-prose ")]')->item(0);
        if (! $content) {
            throw new \RuntimeException('The rendered page has no .doc-prose article body.');
        }

        $body = $this->convertChildren($content, $key);
        $body = $this->normalizeMarkdown($body);

        return '# '.$page['title']."\n\n".$page['blurb']."\n\n".$body;
    }

    private function buildRouteFallbacks(Filesystem $files): array
    {
        $fallbacks = [];

        foreach (config('docs.pages', []) as $page) {
            if (! is_array($page)) {
                continue;
            }

            $route = $page['route'] ?? null;
            $path = $page['path'] ?? null;
            if (is_string($route) && is_string($path)) {
                $fallbacks[$route] = $path;
            }
        }

        $routesFile = base_path('routes/web.php');
        if ($files->exists($routesFile)) {
            $source = $files->get($routesFile);
            preg_match_all(
                "/Route::(?:get|post|put|patch|delete|options|match|any|redirect)\\(\\s*'([^']*)'[\\s\\S]*?->name\\(\\s*'([^']+)'\\s*\\)/",
                $source,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $path = trim($match[1]);
                $name = trim($match[2]);
                if ($path === '' || $name === '') {
                    continue;
                }
                $fallbacks[$name] = str_starts_with($path, '/') ? $path : '/'.$path;
            }
        }

        return $fallbacks;
    }

    private function fallbackUrlForRoute(string $name, bool $absolute): ?string
    {
        $path = $this->routeFallbacks[$name] ?? null;

        if (! is_string($path)) {
            return null;
        }

        if (preg_match('/^https?:\/\//', $path)) {
            return $path;
        }

        $normalizedPath = str_starts_with($path, '/') ? $path : '/'.$path;

        return $absolute ? url($normalizedPath) : $normalizedPath;
    }

    private function convertChildren(DOMNode $parent, string $currentKey, int $listDepth = 0): string
    {
        $markdown = '';
        foreach ($parent->childNodes as $child) {
            $markdown .= $this->convertNode($child, $currentKey, $listDepth);
        }

        return $markdown;
    }

    private function convertNode(DOMNode $node, string $currentKey, int $listDepth = 0): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return preg_replace('/[\t\r\n ]+/u', ' ', $node->nodeValue ?? '') ?? '';
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        $classes = preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [];
        if (in_array($tag, ['script', 'style', 'noscript', 'svg'], true)
            || in_array('sr-only', $classes, true)
            || $node->getAttribute('aria-hidden') === 'true') {
            return '';
        }

        if (str_starts_with($tag, 'x-')) {
            $this->warnings[] = "Unrendered component <{$tag}>; its text content was retained.";
        }

        if (preg_match('/^h([1-6])$/', $tag, $heading)) {
            $text = trim($this->convertChildren($node, $currentKey));
            $anchor = $node->getAttribute('id');
            $prefix = $anchor !== '' ? '<a id="'.$this->escapeHtml($anchor).'"></a>'."\n\n" : '';

            return $prefix.str_repeat('#', (int) $heading[1]).' '.$text."\n\n";
        }

        if ($tag === 'p') {
            return trim($this->convertChildren($node, $currentKey))."\n\n";
        }

        if ($tag === 'ul' || $tag === 'ol') {
            return $this->convertList($node, $currentKey, $listDepth)."\n";
        }

        if ($tag === 'li') {
            return trim($this->convertChildren($node, $currentKey, $listDepth));
        }

        if ($tag === 'strong' || $tag === 'b') {
            return '**'.trim($this->convertChildren($node, $currentKey)).'**';
        }

        if ($tag === 'em' || $tag === 'i') {
            return '*'.trim($this->convertChildren($node, $currentKey)).'*';
        }

        if ($tag === 'del' || $tag === 's') {
            return '~~'.trim($this->convertChildren($node, $currentKey)).'~~';
        }

        if ($tag === 'code') {
            $text = $node->textContent;
            $ticks = str_repeat('`', max(1, $this->longestRun($text, '`') + 1));

            return $ticks.$text.$ticks;
        }

        if ($tag === 'pre') {
            $code = $node->textContent;
            $fence = str_repeat('`', max(3, $this->longestRun($code, '`') + 1));
            $language = '';
            $codeNode = $node->getElementsByTagName('code')->item(0);
            if ($codeNode instanceof DOMElement && preg_match('/(?:^|\s)language-([\w+-]+)/', $codeNode->getAttribute('class'), $match)) {
                $language = $match[1];
            }

            return $fence.$language."\n".rtrim($code)."\n".$fence."\n\n";
        }

        if ($tag === 'a') {
            $text = trim($this->convertChildren($node, $currentKey));
            $href = $this->markdownLink($node->getAttribute('href'), $currentKey);

            return $href === '' ? $text : '['.$text.']('.$href.')';
        }

        if ($tag === 'img') {
            return $this->markdownImage($node->getAttribute('src'), $node->getAttribute('alt'));
        }

        if ($tag === 'figure') {
            $image = $node->getElementsByTagName('img')->item(0);
            $markdown = $image instanceof DOMElement
                ? $this->markdownImage($image->getAttribute('src'), $image->getAttribute('alt'))
                : '';
            $caption = $node->getElementsByTagName('figcaption')->item(0);
            if ($caption) {
                $markdown .= "\n\n*".trim($this->convertChildren($caption, $currentKey)).'*';
            }

            return $markdown."\n\n";
        }

        if ($tag === 'br') {
            return "  \n";
        }

        if ($tag === 'hr') {
            return "\n---\n\n";
        }

        if ($tag === 'blockquote') {
            $text = trim($this->convertChildren($node, $currentKey));
            $lines = preg_split('/\r?\n/', $text) ?: [];

            return implode("\n", array_map(fn (string $line) => '> '.$line, $lines))."\n\n";
        }

        if ($tag === 'table') {
            return $this->convertTable($node, $currentKey)."\n\n";
        }

        if ($tag === 'details') {
            $summary = $node->getElementsByTagName('summary')->item(0);
            $title = $summary ? trim($this->convertChildren($summary, $currentKey)) : 'Details';
            $content = '';
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'summary') {
                    continue;
                }
                $content .= $this->convertNode($child, $currentKey, $listDepth);
            }

            return '**'.$title."**\n\n".trim($content)."\n\n";
        }

        if (in_array('doc-callout-title', $classes, true)) {
            return '**'.trim($this->convertChildren($node, $currentKey))."**\n\n";
        }

        $content = $this->convertChildren($node, $currentKey, $listDepth);
        if ($tag === 'div' || $tag === 'section' || $tag === 'article' || $tag === 'main') {
            return trim($content) === '' ? '' : trim($content)."\n\n";
        }

        return $content;
    }

    private function convertList(DOMElement $list, string $currentKey, int $depth): string
    {
        $ordered = strtolower($list->tagName) === 'ol';
        $counter = max(1, (int) $list->getAttribute('start'));
        $lines = [];
        $indent = str_repeat('  ', $depth);

        foreach ($list->childNodes as $item) {
            if (! $item instanceof DOMElement || strtolower($item->tagName) !== 'li') {
                continue;
            }

            $text = '';
            $nested = '';
            foreach ($item->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['ul', 'ol'], true)) {
                    $nested .= $this->convertList($child, $currentKey, $depth + 1);
                } else {
                    $text .= $this->convertNode($child, $currentKey, $depth);
                }
            }

            $marker = $ordered ? $counter++.'. ' : '- ';
            $textLines = preg_split('/\r?\n/', trim($text)) ?: [''];
            $lines[] = $indent.$marker.array_shift($textLines);
            foreach ($textLines as $line) {
                $lines[] = $indent.'  '.$line;
            }
            if (trim($nested) !== '') {
                $lines[] = rtrim($nested);
            }
        }

        return implode("\n", $lines);
    }

    private function convertTable(DOMElement $table, string $currentKey): string
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if (! $cell instanceof DOMElement || ! in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                    continue;
                }
                $text = trim($this->convertChildren($cell, $currentKey));
                $text = preg_replace('/\s*\n\s*/', '<br>', $text) ?? $text;
                $cells[] = str_replace('|', '\\|', $text);
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        if ($rows === []) {
            return '';
        }

        $width = max(array_map('count', $rows));
        foreach ($rows as &$row) {
            $row = array_pad($row, $width, '');
        }
        unset($row);

        $header = array_shift($rows);
        $output = ['| '.implode(' | ', $header).' |', '| '.implode(' | ', array_fill(0, $width, '---')).' |'];
        foreach ($rows as $row) {
            $output[] = '| '.implode(' | ', $row).' |';
        }

        return implode("\n", $output);
    }

    private function markdownLink(string $href, string $currentKey): string
    {
        if ($href === '') {
            return '';
        }

        $parts = parse_url(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [];
        $path = $parts['path'] ?? '';
        $normalizedPath = $this->normalizePath($path);
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        if (isset($this->guidePaths[$normalizedPath])) {
            $targetKey = $this->guidePaths[$normalizedPath];

            return $targetKey === $currentKey
                ? ($fragment !== '' ? $fragment : '#')
                : basename($targetKey).'.md'.$fragment;
        }

        if (! isset($parts['host']) || $parts['host'] === parse_url(config('app.url'), PHP_URL_HOST)) {
            return ($path !== '' ? $path : '').(isset($parts['query']) ? '?'.$parts['query'] : '').$fragment;
        }

        return $href;
    }

    private function markdownImage(string $src, string $alt): string
    {
        $parts = parse_url(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [];
        $path = $parts['path'] ?? $src;
        if (str_starts_with($path, '/images/docs/')) {
            $path = '../../../public'.$path;
        }

        return '!['.str_replace(']', '\\]', $alt).']('.str_replace(' ', '%20', $path).')';
    }

    private function normalizePath(string $path): string
    {
        return '/'.trim(rawurldecode($path), '/');
    }

    private function normalizeMarkdown(string $markdown): string
    {
        $markdown = preg_replace('/[ \t]+\n/', "\n", $markdown) ?? $markdown;
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown) ?? $markdown;

        return trim($markdown)."\n";
    }

    private function longestRun(string $text, string $character): int
    {
        preg_match_all('/'.preg_quote($character, '/').'+/', $text, $matches);

        return max(array_map('strlen', $matches[0] ?: ['']));
    }

    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}