<?php
declare(strict_types=1);

// Nemesis 5.0.0 — Phase 11 Test Suite | Created: 2026-04-03
// Tests: Lexer, Compiler, Engine, View facade, layout inheritance, directives

use Nemesis\Testing\TestCase;
use Nemesis\Tokenizer\Lexer;
use Nemesis\Tokenizer\Token;
use Nemesis\Tokenizer\TokenType;
use Nemesis\View\Compiler;
use Nemesis\View\DirectiveRegistry;
use Nemesis\View\Engine;
use Nemesis\Core\View;

// ===========================================================================
// Phase11LexerTest — tokenizer correctness
// ===========================================================================

class Phase11LexerTest extends TestCase
{
    private Lexer $lexer;

    public function setUp(): void
    {
        $this->lexer = new Lexer();
    }

    public function testTokenizesPlainText(): void
    {
        $tokens = $this->lexer->tokenize('<p>Hello</p>');
        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::TEXT, $tokens[0]->type);
        $this->assertSame('<p>Hello</p>', $tokens[0]->value);
    }

    public function testTokenizesEscapedEcho(): void
    {
        $tokens = $this->lexer->tokenize('{{ $name }}');
        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::ECHO_ESCAPED, $tokens[0]->type);
        $this->assertSame('{{ $name }}', $tokens[0]->value);
    }

    public function testTokenizesRawEcho(): void
    {
        $tokens = $this->lexer->tokenize('{!! $html !!}');
        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::ECHO_RAW, $tokens[0]->type);
    }

    public function testTokenizesComment(): void
    {
        $tokens = $this->lexer->tokenize('{{-- this is a comment --}}');
        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::COMMENT, $tokens[0]->type);
    }

    public function testTokenizesDirectiveWithArgs(): void
    {
        $tokens = $this->lexer->tokenize('@if($x > 0)');
        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::DIRECTIVE, $tokens[0]->type);
        $this->assertSame('@if($x > 0)', $tokens[0]->value);
    }

    public function testTokenizesDirectiveWithoutArgs(): void
    {
        $tokens = $this->lexer->tokenize('@endif');
        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::DIRECTIVE, $tokens[0]->type);
        $this->assertSame('@endif', $tokens[0]->value);
    }

    public function testTokenizesDirectiveWithNestedParens(): void
    {
        $tokens = $this->lexer->tokenize('@if(count($items) > 0)');
        $this->assertCount(1, $tokens);
        $this->assertSame('@if(count($items) > 0)', $tokens[0]->value);
    }

    public function testMixedTemplate(): void
    {
        $source = '<h1>{{ $title }}</h1>{{-- comment --}}<p>{!! $body !!}</p>';
        $tokens = $this->lexer->tokenize($source);

        // TEXT: <h1>
        // ECHO_ESCAPED: {{ $title }}
        // TEXT: </h1>
        // COMMENT: {{-- comment --}}
        // TEXT: <p>
        // ECHO_RAW: {!! $body !!}
        // TEXT: </p>
        $this->assertCount(7, $tokens);
        $this->assertSame(TokenType::TEXT,         $tokens[0]->type);
        $this->assertSame(TokenType::ECHO_ESCAPED,  $tokens[1]->type);
        $this->assertSame(TokenType::TEXT,          $tokens[2]->type);
        $this->assertSame(TokenType::COMMENT,       $tokens[3]->type);
        $this->assertSame(TokenType::TEXT,          $tokens[4]->type);
        $this->assertSame(TokenType::ECHO_RAW,      $tokens[5]->type);
        $this->assertSame(TokenType::TEXT,          $tokens[6]->type);
    }

    public function testCommentIsNotConfusedWithEcho(): void
    {
        $tokens = $this->lexer->tokenize('{{-- not {{ echo }} --}}');
        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::COMMENT, $tokens[0]->type);
    }

    public function testLineNumberTracking(): void
    {
        $source = "line1\nline2\n{{ \$x }}";
        $tokens = $this->lexer->tokenize($source);
        // Last token is the echo on line 3
        $echoToken = end($tokens);
        $this->assertSame(TokenType::ECHO_ESCAPED, $echoToken->type);
        $this->assertSame(3, $echoToken->line);
    }

    public function testExtractParensNestedParens(): void
    {
        [$extracted] = $this->lexer->extractParens('(count($x) > 0) rest', 0);
        $this->assertSame('(count($x) > 0)', $extracted);
    }

    public function testExtractParensWithStringLiteral(): void
    {
        [$extracted] = $this->lexer->extractParens('("he said (hi)")', 0);
        $this->assertSame('("he said (hi)")', $extracted);
    }
}

// ===========================================================================
// Phase11CompilerTest — template → PHP compilation
// ===========================================================================

class Phase11CompilerTest extends TestCase
{
    private Compiler $compiler;

    public function setUp(): void
    {
        $this->compiler = new Compiler();
    }

    private function compile(string $tpl): string
    {
        return $this->compiler->compile($tpl);
    }

    // ── Echo ──────────────────────────────────────────────────────────────────

    public function testEscapedEcho(): void
    {
        $php = $this->compile('{{ $name }}');
        $this->assertStringContainsString('htmlspecialchars', $php);
        $this->assertStringContainsString('$name', $php);
    }

    public function testRawEcho(): void
    {
        $php = $this->compile('{!! $html !!}');
        $this->assertStringContainsString('echo $html', $php);
        $this->assertStringNotContainsString('htmlspecialchars', $php);
    }

    public function testCommentIsStripped(): void
    {
        $php = $this->compile('{{-- secret --}}');
        $this->assertSame('', $php);
    }

    public function testCommentAmidText(): void
    {
        $php = $this->compile('before{{-- x --}}after');
        $this->assertSame('beforeafter', $php);
    }

    // ── Control ───────────────────────────────────────────────────────────────

    public function testIfDirective(): void
    {
        $php = $this->compile('@if($x > 0)yes@endif');
        $this->assertStringContainsString('<?php if($x > 0): ?>', $php);
        $this->assertStringContainsString('<?php endif; ?>', $php);
    }

    public function testElseIfElse(): void
    {
        $php = $this->compile('@if($a)A@elseif($b)B@else C@endif');
        $this->assertStringContainsString('elseif($b):', $php);
        $this->assertStringContainsString('else:', $php);
    }

    public function testUnless(): void
    {
        $php = $this->compile('@unless($x)no@endunless');
        $this->assertStringContainsString('if(!($x)):', $php);
        $this->assertStringContainsString('endif;', $php);
    }

    // ── Loops ─────────────────────────────────────────────────────────────────

    public function testForeach(): void
    {
        $php = $this->compile('@foreach($items as $i){{ $i }}@endforeach');
        $this->assertStringContainsString('foreach($items as $i):', $php);
        $this->assertStringContainsString('endforeach;', $php);
    }

    public function testFor(): void
    {
        $php = $this->compile('@for($i=0;$i<3;$i++){{ $i }}@endfor');
        $this->assertStringContainsString('for($i=0;$i<3;$i++):', $php);
        $this->assertStringContainsString('endfor;', $php);
    }

    public function testWhile(): void
    {
        $php = $this->compile('@while($running)ok@endwhile');
        $this->assertStringContainsString('while($running):', $php);
        $this->assertStringContainsString('endwhile;', $php);
    }

    public function testBreakContinue(): void
    {
        $php = $this->compile('@break @continue');
        $this->assertStringContainsString('break;', $php);
        $this->assertStringContainsString('continue;', $php);
    }

    // ── Forms ─────────────────────────────────────────────────────────────────

    public function testCsrf(): void
    {
        $php = $this->compile('@csrf');
        $this->assertStringContainsString('_token', $php);
        $this->assertStringContainsString('hidden', $php);
    }

    public function testMethod(): void
    {
        $php = $this->compile("@method('PUT')");
        $this->assertStringContainsString('_method', $php);
        $this->assertStringContainsString('strtoupper', $php);
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public function testVite(): void
    {
        $php = $this->compile("@vite('resources/js/app.js')");
        $this->assertStringContainsString("vite(", $php);
    }

    public function testAsset(): void
    {
        $php = $this->compile("@asset('img/logo.png')");
        $this->assertStringContainsString("asset(", $php);
    }

    // ── Layout directives ─────────────────────────────────────────────────────

    public function testExtends(): void
    {
        $php = $this->compile("@extends('layouts.app')");
        $this->assertStringContainsString('setLayout', $php);
        $this->assertStringContainsString("'layouts.app'", $php);
    }

    public function testSection(): void
    {
        $php = $this->compile("@section('content')hello@endsection");
        $this->assertStringContainsString('startSection', $php);
        $this->assertStringContainsString('ob_start', $php);
        $this->assertStringContainsString('endSection', $php);
    }

    public function testInlineSection(): void
    {
        $php = $this->compile("@section('title', 'My Page')");
        $this->assertStringContainsString('setSection', $php);
        $this->assertStringNotContainsString('ob_start', $php);
    }

    public function testYield(): void
    {
        $php = $this->compile("@yield('content')");
        $this->assertStringContainsString('yieldSection', $php);
        $this->assertStringContainsString("'content'", $php);
    }

    public function testYieldWithDefault(): void
    {
        $php = $this->compile("@yield('title', 'Default')");
        $this->assertStringContainsString('yieldSection', $php);
        $this->assertStringContainsString("'Default'", $php);
    }

    // ── PHP block ─────────────────────────────────────────────────────────────

    public function testPhpBlock(): void
    {
        $php = $this->compile('@php $x = 1; @endphp');
        $this->assertStringContainsString('<?php', $php);
        $this->assertStringContainsString('?>', $php);
    }

    // ── Custom directive ──────────────────────────────────────────────────────

    public function testCustomDirective(): void
    {
        DirectiveRegistry::register('upper', fn($args) => "<?php echo strtoupper{$args}; ?>");
        $php = $this->compile("@upper('hello')");
        $this->assertStringContainsString('strtoupper', $php);
        DirectiveRegistry::forget('upper');
    }

    // ── Unknown directive passthrough ─────────────────────────────────────────

    public function testUnknownDirectivePassthrough(): void
    {
        $php = $this->compile('@livewire');
        $this->assertStringContainsString('@livewire', $php);
    }

    // ── Full round-trip (compile + eval) ──────────────────────────────────────

    public function testEscapedEchoOutput(): void
    {
        $php = $this->compile('Hello {{ $name }}!');
        ob_start();
        $name = '<World>';
        eval('?>' . $php);
        $out = ob_get_clean();
        $this->assertSame('Hello &lt;World&gt;!', $out);
    }

    public function testRawEchoOutput(): void
    {
        $php = $this->compile('{!! $html !!}');
        ob_start();
        $html = '<b>bold</b>';
        eval('?>' . $php);
        $out = ob_get_clean();
        $this->assertSame('<b>bold</b>', $out);
    }

    public function testIfOutput(): void
    {
        $php  = $this->compile('@if($show)YES@endif');
        ob_start();
        $show = true;
        eval('?>' . $php);
        $out = ob_get_clean();
        $this->assertSame('YES', $out);
    }

    public function testForeachOutput(): void
    {
        $php = $this->compile('@foreach($items as $item){{ $item }},@endforeach');
        ob_start();
        $items = ['a', 'b', 'c'];
        eval('?>' . $php);
        $out = ob_get_clean();
        $this->assertSame('a,b,c,', $out);
    }
}

// ===========================================================================
// Phase11EngineTest — Engine (compile cache, render, layout, includes)
// ===========================================================================

class Phase11EngineTest extends TestCase
{
    private Engine  $engine;
    private string  $viewDir;
    private string  $cacheDir;
    private array   $tmpFiles = [];

    public function setUp(): void
    {
        $this->viewDir  = sys_get_temp_dir() . '/nemesis-views-'  . uniqid();
        $this->cacheDir = sys_get_temp_dir() . '/nemesis-cache-'  . uniqid();
        mkdir($this->viewDir,  0755, true);
        mkdir($this->cacheDir, 0755, true);

        $this->engine = new Engine([$this->viewDir], $this->cacheDir);
    }

    public function tearDown(): void
    {
        // Clean up tmp dirs
        foreach (glob($this->viewDir  . '/*') as $f) @unlink($f);
        foreach (glob($this->cacheDir . '/*') as $f) @unlink($f);
        @rmdir($this->viewDir);
        @rmdir($this->cacheDir);
    }

    private function writeView(string $name, string $content): string
    {
        $path = $this->viewDir . '/' . $name . '.blade.php';
        file_put_contents($path, $content);
        return $path;
    }

    // ── Resolution ────────────────────────────────────────────────────────────

    public function testFindViewDotNotation(): void
    {
        $this->writeView('greet', 'hi');
        $path = $this->engine->findView('greet');
        $this->assertStringContainsString('greet.blade.php', $path);
    }

    public function testFindViewNestedDotNotation(): void
    {
        mkdir($this->viewDir . '/admin', 0755, true);
        file_put_contents($this->viewDir . '/admin/dashboard.blade.php', 'dash');
        $path = $this->engine->findView('admin.dashboard');
        $this->assertStringContainsString('admin/dashboard.blade.php', $path);
        @unlink($this->viewDir . '/admin/dashboard.blade.php');
        @rmdir($this->viewDir . '/admin');
    }

    public function testFindViewNamespaced(): void
    {
        $nsDir = sys_get_temp_dir() . '/nemesis-ns-' . uniqid();
        mkdir($nsDir, 0755, true);
        file_put_contents($nsDir . '/panel.blade.php', 'panel');
        $this->engine->addNamespace('admin', $nsDir);
        $path = $this->engine->findView('admin::panel');
        $this->assertStringContainsString('panel.blade.php', $path);
        @unlink($nsDir . '/panel.blade.php');
        @rmdir($nsDir);
    }

    public function testFindViewThrowsWhenMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->engine->findView('nonexistent.view');
    }

    public function testExistsReturnsTrueForValid(): void
    {
        $this->writeView('home', '<h1>Home</h1>');
        $this->assertTrue($this->engine->exists('home'));
    }

    public function testExistsReturnsFalseForMissing(): void
    {
        $this->assertFalse($this->engine->exists('ghost'));
    }

    // ── Compilation / caching ─────────────────────────────────────────────────

    public function testCompileCreatesCacheFile(): void
    {
        $view = $this->writeView('simple', '<p>{{ $x }}</p>');
        $cached = $this->engine->compile($view);
        $this->assertTrue(file_exists($cached), "Cache file should exist at: {$cached}");
    }

    public function testCacheHitDoesNotRecompile(): void
    {
        $view  = $this->writeView('cached', 'Hello');
        $path1 = $this->engine->compile($view);
        $mtime1 = filemtime($path1);
        sleep(1); // ensure mtime would differ if recompiled
        $path2 = $this->engine->compile($view);
        $this->assertSame($path1, $path2);
        $this->assertSame($mtime1, filemtime($path2));
    }

    public function testCacheInvalidatedOnSourceChange(): void
    {
        $view  = $this->writeView('changing', 'v1');
        $path1 = $this->engine->compile($view);
        sleep(1);
        file_put_contents($view, 'v2');
        touch($view, time() + 2); // ensure mtime is newer
        $path2 = $this->engine->compile($view);
        $this->assertSame($path1, $path2); // same hash, same file path
        $this->assertStringContainsString('v2', file_get_contents($path2));
    }

    // ── Basic rendering ───────────────────────────────────────────────────────

    public function testRenderPlainText(): void
    {
        $this->writeView('plain', '<p>Hello World</p>');
        $html = $this->engine->render('plain');
        $this->assertSame('<p>Hello World</p>', $html);
    }

    public function testRenderWithData(): void
    {
        $this->writeView('greeting', '<p>{{ $name }}</p>');
        $html = $this->engine->render('greeting', ['name' => 'Jarir']);
        $this->assertSame('<p>Jarir</p>', $html);
    }

    public function testRenderEscapesHtml(): void
    {
        $this->writeView('safe', '{{ $x }}');
        $html = $this->engine->render('safe', ['x' => '<script>']);
        $this->assertSame('&lt;script&gt;', $html);
    }

    public function testRenderRawEcho(): void
    {
        $this->writeView('raw', '{!! $x !!}');
        $html = $this->engine->render('raw', ['x' => '<b>bold</b>']);
        $this->assertSame('<b>bold</b>', $html);
    }

    public function testRenderIf(): void
    {
        $this->writeView('ifview', '@if($show)YES@endif');
        $this->assertSame('YES', $this->engine->render('ifview', ['show' => true]));
        $this->assertSame('',    $this->engine->render('ifview', ['show' => false]));
    }

    public function testRenderForeach(): void
    {
        $this->writeView('loop', '@foreach($items as $item){{ $item }},@endforeach');
        $html = $this->engine->render('loop', ['items' => ['a', 'b', 'c']]);
        $this->assertSame('a,b,c,', $html);
    }

    // ── @include ──────────────────────────────────────────────────────────────

    public function testInclude(): void
    {
        $this->writeView('partial', '<span>{{ $msg }}</span>');
        $this->writeView('parent-inc', "@include('partial', ['msg' => 'hi'])");
        $html = $this->engine->render('parent-inc');
        $this->assertSame('<span>hi</span>', $html);
    }

    public function testIncludeIf(): void
    {
        $this->writeView('base-incif', "@includeIf('nonexistent.view')done");
        $html = $this->engine->render('base-incif');
        $this->assertSame('done', $html);
    }

    // ── Layout inheritance ────────────────────────────────────────────────────

    public function testLayoutInheritance(): void
    {
        $this->writeView('layouts-app', '<html><body>@yield(\'content\')</body></html>');
        $this->writeView('child', "@extends('layouts-app')@section('content')BODY@endsection");
        $html = $this->engine->render('child');
        $this->assertSame('<html><body>BODY</body></html>', $html);
    }

    public function testLayoutWithMultipleSections(): void
    {
        $this->writeView('layouts-full',
            '<title>@yield(\'title\')</title><body>@yield(\'content\')</body>');
        $this->writeView('page',
            "@extends('layouts-full')" .
            "@section('title', 'My Page')" .
            "@section('content')Hello@endsection");
        $html = $this->engine->render('page');
        $this->assertStringContainsString('<title>My Page</title>', $html);
        $this->assertStringContainsString('<body>Hello</body>', $html);
    }

    public function testYieldDefault(): void
    {
        $this->writeView('layouts-default', "@yield('sidebar', 'no sidebar')");
        $this->writeView('nosidebar', "@extends('layouts-default')");
        $html = $this->engine->render('nosidebar');
        $this->assertSame('no sidebar', $html);
    }

    public function testHasSection(): void
    {
        $this->writeView('layouts-has',
            '@hasSection(\'hero\')HAS@endif' .
            '@yield(\'main\')');
        $this->writeView('with-hero',
            "@extends('layouts-has')" .
            "@section('hero')HeroContent@endsection" .
            "@section('main')Main@endsection");
        $html = $this->engine->render('with-hero');
        $this->assertStringContainsString('HAS', $html);
    }

    // ── Section state reset ───────────────────────────────────────────────────

    public function testResetState(): void
    {
        $this->engine->startSection('x');
        ob_start(); echo 'data'; $this->engine->endSection();
        $this->assertNotEmpty($this->engine->getSections());
        $this->engine->resetState();
        $this->assertEmpty($this->engine->getSections());
    }
}

// ===========================================================================
// Phase11DirectiveRegistryTest — custom directive extension
// ===========================================================================

class Phase11DirectiveRegistryTest extends TestCase
{
    public function tearDown(): void
    {
        DirectiveRegistry::forget('money');
    }

    public function testRegisterAndHas(): void
    {
        DirectiveRegistry::register('money', fn($a) => "<?php echo number_format{$a}; ?>");
        $this->assertTrue(DirectiveRegistry::has('money'));
    }

    public function testCallDirective(): void
    {
        DirectiveRegistry::register('money', fn($a) => "echo_money{$a}");
        $result = DirectiveRegistry::call('money', '(1000)');
        $this->assertSame('echo_money(1000)', $result);
    }

    public function testForgetRemoves(): void
    {
        DirectiveRegistry::register('money', fn($a) => '');
        DirectiveRegistry::forget('money');
        $this->assertFalse(DirectiveRegistry::has('money'));
    }

    public function testCaseInsensitiveLookup(): void
    {
        DirectiveRegistry::register('Money', fn($a) => 'ok');
        $this->assertTrue(DirectiveRegistry::has('money'));
        $this->assertTrue(DirectiveRegistry::has('MONEY'));
    }
}

// ===========================================================================
// Phase11ViewFacadeTest — View static facade
// ===========================================================================

class Phase11ViewFacadeTest extends TestCase
{
    private string $viewDir;
    private string $cacheDir;

    public function setUp(): void
    {
        $this->viewDir  = sys_get_temp_dir() . '/nemesis-facade-' . uniqid();
        $this->cacheDir = sys_get_temp_dir() . '/nemesis-facache-' . uniqid();
        mkdir($this->viewDir,  0755, true);
        mkdir($this->cacheDir, 0755, true);

        // Point the singleton Engine at our tmp dirs
        $engine = new Engine([$this->viewDir], $this->cacheDir);
        $ref = new \ReflectionProperty(Engine::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $engine);
    }

    public function tearDown(): void
    {
        // Reset singleton
        $ref = new \ReflectionProperty(Engine::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        foreach (glob($this->viewDir  . '/*') as $f) @unlink($f);
        foreach (glob($this->cacheDir . '/*') as $f) @unlink($f);
        @rmdir($this->viewDir);
        @rmdir($this->cacheDir);
    }

    public function testViewMake(): void
    {
        file_put_contents($this->viewDir . '/hello.blade.php', 'Hello {{ $who }}!');
        $html = View::make('hello', ['who' => 'World']);
        $this->assertSame('Hello World!', $html);
    }

    public function testViewRenderEchoes(): void
    {
        file_put_contents($this->viewDir . '/echo-view.blade.php', 'rendered');
        ob_start();
        View::render('echo-view');
        $out = ob_get_clean();
        $this->assertSame('rendered', $out);
    }

    public function testViewExists(): void
    {
        file_put_contents($this->viewDir . '/existing.blade.php', 'x');
        $this->assertTrue(View::exists('existing'));
        $this->assertFalse(View::exists('ghost'));
    }

    public function testViewEngineReturnsSingleton(): void
    {
        $this->assertInstanceOf(Engine::class, View::engine());
        $this->assertSame(View::engine(), View::engine());
    }
}
