<?php

declare(strict_types=1);

use Preflight\Core\Analyzer;
use Preflight\Core\Render\MarkdownRenderer;
use Preflight\Core\Render\Sources;
use Preflight\Core\Render\TextRenderer;
use Preflight\Core\Report\Report;
use Preflight\Core\Schema\SchemaSnapshot;
use Preflight\Core\Server\ServerProfile;
use Preflight\Core\Stats\StatsSet;

function renderFixtureReport(bool $withStats = false): Report
{
    $schema = SchemaSnapshot::fromSql(<<<'SQL'
        CREATE TABLE `orders` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `status` varchar(50) NOT NULL DEFAULT 'pending',
          `note` varchar(255) DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

    return (new Analyzer)->analyze([
        "alter table `orders` modify `status` varchar(100) not null default 'pending'",
        'alter table `orders` add `shipped_at` timestamp null',
        "create table `refunds` (`id` bigint unsigned not null primary key comment 'a | b')",
    ], ServerProfile::mysql('8.0.36'), $schema, $withStats ? StatsSet::fromArray(['orders' => ['rows' => 1_000_000, 'data_bytes' => 2 * 1024 ** 3, 'index_bytes' => 0]]) : null);
}

it('renders plain text grouped by source', function () {
    $text = (new TextRenderer)->render(renderFixtureReport(), new Sources([0 => '2026_01_01_widen_status', 1 => '2026_01_02_add_shipped_at', 2 => '2026_01_02_add_shipped_at']));

    expect($text)
        ->toContain("2026_01_01_widen_status\nDANGER alter table `orders` modify `status`")
        ->toContain('ALGORITHM=COPY LOCK=SHARED · rebuilds table · BLOCKS WRITES')
        ->toContain("2026_01_02_add_shipped_at\nOK     alter table `orders` add `shipped_at`")
        ->toContain('Overall: DANGER')
        ->not->toContain("\033[");
});

it('renders a markdown PR comment', function () {
    $markdown = (new MarkdownRenderer)->render(renderFixtureReport(), new Sources([0 => '2026_01_01_widen_status']));

    expect($markdown)
        ->toStartWith(MarkdownRenderer::MARKER."\n### 🔴 Preflight: 1 risky statement")
        ->toContain('static analysis')
        ->toContain('| 🔴 | <code>alter table `orders` modify `status` varchar(100)')
        ->toContain('<br><sub>2026_01_01_widen_status</sub>')
        ->toContain('COPY · **blocks writes** · rebuilds table')
        ->toContain("```sql\nalter table `orders` modify")
        ->toContain('  - 💡 Add a new column')
        ->toContain('safe statements</summary>')
        // pipes inside SQL must not break the table
        ->toContain("'a &#124; b'")
        ->toContain('<summary>Notes (1)</summary>');
});

it('adds an estimate column when sizes are known', function () {
    $markdown = (new MarkdownRenderer)->render(renderFixtureReport(withStats: true));

    expect($markdown)->toContain('| | Statement | How MySQL runs it | Estimate |')
        ->toContain('size-aware');
});

it('says when there is nothing to check', function () {
    $report = (new Analyzer)->analyze([], ServerProfile::mysql('8.0.36'));

    expect((new MarkdownRenderer)->render($report))->toContain('🟢 Preflight: no migration statements to check')
        ->and((new TextRenderer)->render($report))->toContain('No statements to analyse.');
});
