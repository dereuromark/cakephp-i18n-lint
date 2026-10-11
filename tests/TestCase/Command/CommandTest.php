<?php

declare(strict_types=1);

namespace I18nLint\Test\TestCase\Command;

use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\StubConsoleOutput;
use Cake\Utility\Filesystem;
use I18nLint\Runner;
use PHPUnit\Framework\TestCase;

class CommandTest extends TestCase
{
    protected string $root;

    protected string $out = '';

    protected string $err = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Keep the default output format stable when the suite itself runs on GitHub Actions.
        putenv('GITHUB_ACTIONS=false');
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'i18n-lint-test-' . bin2hex(random_bytes(6));
        (new Filesystem())->copyDir(dirname(__DIR__, 2) . '/test_files/plugin', $this->root);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDir($this->root);

        parent::tearDown();
    }

    public function testUsage(): void
    {
        $this->assertSame(0, $this->runCli([]));
        $this->assertStringContainsString('extract', $this->out);

        $this->assertSame(1, $this->runCli(['nope']));
        $this->assertStringContainsString('Unknown command `nope`', $this->err);
    }

    public function testInvalidRoot(): void
    {
        $this->assertSame(1, $this->runCli(['check', '--root', $this->root . '/missing']));
        $this->assertStringContainsString('does not exist', $this->err);
    }

    public function testCheckClean(): void
    {
        $this->assertSame(0, $this->exec('check'), $this->out . $this->err);
        $this->assertStringContainsString('up to date', $this->out);
    }

    public function testCheckIgnoresHeaderAndOrder(): void
    {
        $pot = $this->read('resources/locales/demo.pot');
        $pot = str_replace('2026-01-01', '2031-05-05', $pot);
        $welcome = "msgid \"Welcome\"\nmsgstr \"\"\n\n";
        $pot = str_replace($welcome, '', $pot);
        $pot = str_replace("msgid \"Saved.\"\n", $welcome . "msgid \"Saved.\"\n", $pot);
        $this->write('resources/locales/demo.pot', $pot);

        $this->assertSame(0, $this->exec('check'), $this->out . $this->err);
    }

    public function testCheckReportsDriftAndExtractFixesIt(): void
    {
        $template = $this->read('templates/Demo/index.php');
        $this->write('templates/Demo/index.php', "<?= __d('demo', 'Brand new') ?>\n<?= __d('other', 'Elsewhere') ?>\n");
        $before = $this->read('resources/locales/demo.pot');

        $this->assertSame(1, $this->exec('check'));
        $this->assertStringContainsString('resources/locales/demo.pot: error: "Brand new" is missing from the template. [pot.missing]', $this->out);
        $this->assertStringContainsString('"Welcome" is no longer in the source code. [pot.stale]', $this->out);
        $this->assertStringContainsString('resources/locales/other.pot: error: Template does not exist yet (1 strings). [pot.missing-file]', $this->out);
        $this->assertStringContainsString('3 difference(s)', $this->err);
        $this->assertSame($before, $this->read('resources/locales/demo.pot'));

        $this->assertSame(0, $this->exec('extract'));
        $this->assertStringContainsString('demo.pot updated: 1 added, 1 removed.', $this->out);
        $this->assertStringContainsString('other.pot created', $this->out);
        $this->assertStringContainsString('msgid "Brand new"', $this->read('resources/locales/demo.pot'));
        $this->assertSame(0, $this->exec('check'), $this->out . $this->err);

        // Back to the original source: other.pot is now an orphan, which only warns.
        $this->write('templates/Demo/index.php', $template);
        $this->assertSame(0, $this->exec('extract'));
        $this->assertStringContainsString('other.pot has no strings', $this->err);
        $this->assertSame(0, $this->exec('check'));
        $this->assertStringContainsString('[pot.orphan]', $this->out);
    }

    public function testCheckDetectsPluralChange(): void
    {
        $this->write('resources/locales/demo.pot', str_replace(
            "msgid_plural \"{0} items\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n",
            "msgstr \"\"\n",
            $this->read('resources/locales/demo.pot'),
        ));

        $this->assertSame(1, $this->exec('check'));
        $this->assertStringContainsString('"{0} item / {0} items" is missing', $this->out);
    }

    public function testExtractLeavesUnchangedTemplateAlone(): void
    {
        $file = $this->root . '/resources/locales/demo.pot';
        touch($file, 1000);
        clearstatcache();

        $this->assertSame(0, $this->exec('extract'));
        $this->assertStringContainsString('demo.pot is up to date', $this->out);
        clearstatcache();
        $this->assertSame(1000, filemtime($file));
    }

    public function testNonLiteralStringIsReported(): void
    {
        $this->write('templates/Demo/index.php', "<?= __d('demo', 'Welcome') ?>\n<?= __d('demo', \$dynamic) ?>\n");

        $this->assertSame(0, $this->exec('check'));
        $this->assertStringContainsString('Invalid marker content in templates/Demo/index.php:2', $this->err);
        $this->assertStringContainsString('1 call(s) with a non-literal string', $this->err);
    }

    public function testExtraPathForStringsUsedDynamically(): void
    {
        $this->write('resources/i18n/strings.php', "<?php\n__d('demo', 'low');\n");

        $this->assertSame(1, $this->exec('check', '--paths', 'src,templates,resources/i18n'));
        $this->assertStringContainsString('"low" is missing from the template.', $this->out);
        $this->assertSame(0, $this->exec('check'));

        $this->assertSame(1, $this->exec('check', '--paths', 'src,nope'));
        $this->assertStringContainsString('Source path `nope` does not exist.', $this->err);
    }

    public function testGithubFormat(): void
    {
        $this->write('templates/Demo/index.php', "<?= __d('demo', 'Welcome') ?>\n<?= __d('demo', 'New') ?>\n");

        $this->assertSame(1, $this->exec('check', '--format', 'github'));
        $this->assertStringContainsString('::error file=resources/locales/demo.pot,title=pot.missing::"New" is missing from the template.', $this->out);
    }

    public function testSync(): void
    {
        $po = $this->read('resources/locales/de/demo.po');
        $this->assertSame(0, $this->exec('sync', '--dry-run'));

        $this->write('resources/locales/demo.pot', str_replace(
            "msgid \"Welcome\"\n",
            "msgid \"Added\"\n",
            $this->read('resources/locales/demo.pot'),
        ));

        $this->assertSame(1, $this->exec('sync', '--dry-run'));
        $this->assertStringContainsString('resources/locales/de/demo.po: 1 to add, 1 to remove.', $this->out);
        $this->assertSame($po, $this->read('resources/locales/de/demo.po'));

        $this->assertSame(0, $this->exec('sync'));
        $expected = str_replace("\n\nmsgid \"Welcome\"\nmsgstr \"Willkommen\"\n", "\n\nmsgid \"Added\"\nmsgstr \"\"\n", $po);
        $this->assertSame($expected, $this->read('resources/locales/de/demo.po'));
        $this->assertSame(0, $this->exec('sync', '--dry-run'));
    }

    public function testLint(): void
    {
        $this->assertSame(0, $this->exec('lint', '--strict'), $this->out . $this->err);
        $this->assertStringContainsString('0 error(s), 0 warning(s).', $this->out);

        $po = $this->read('resources/locales/de/demo.po');
        $this->write('resources/locales/de/demo.po', str_replace('"Willkommen"', '""', $po));
        $this->assertSame(0, $this->exec('lint'));
        $this->assertStringContainsString('resources/locales/de/demo.po:27: warning: "Welcome" is not translated. [po.untranslated]', $this->out);
        $this->assertSame(1, $this->exec('lint', '--strict'));

        $this->write('resources/locales/de/demo.po', str_replace('"Hallo {0}"', '"Hallo {name}"', $po));
        $this->assertSame(1, $this->exec('lint'));
        $this->assertStringContainsString('uses placeholder(s) the source does not have: {name}. [po.placeholder-unknown]', $this->out);
        $this->assertSame(1, $this->exec('lint', '--format', 'github'));
        $this->assertStringContainsString('::error file=resources/locales/de/demo.po,line=15,title=po.placeholder-unknown::', $this->out);
    }

    public function testLintReportsBrokenFiles(): void
    {
        $this->write('resources/locales/de/demo.po', "msgid \"A\"\nmsgstr \"B\n");
        $this->write('resources/locales/fr/demo.po', "msgid \"A\"\nmsgstr \"\xE9\"\n");

        $this->assertSame(1, $this->exec('lint'));
        $this->assertStringContainsString('resources/locales/de/demo.po:2: error: Malformed string, check the quotes. [po.syntax]', $this->out);
        $this->assertStringContainsString('resources/locales/fr/demo.po: error: File is not valid UTF-8. [po.encoding]', $this->out);
    }

    protected function exec(string $command, string ...$options): int
    {
        return $this->runCli([$command, '--root', $this->root, ...$options]);
    }

    /**
     * @param array<string> $argv
     */
    protected function runCli(array $argv): int
    {
        $out = new StubConsoleOutput();
        $err = new StubConsoleOutput();
        $code = (new Runner())->run($argv, new ConsoleIo($out, $err));
        $this->out = implode("\n", $out->messages());
        $this->err = implode("\n", $err->messages());

        return $code;
    }

    protected function read(string $path): string
    {
        return (string)file_get_contents($this->root . '/' . $path);
    }

    protected function write(string $path, string $content): void
    {
        (new Filesystem())->dumpFile($this->root . '/' . $path, $content);
    }
}
