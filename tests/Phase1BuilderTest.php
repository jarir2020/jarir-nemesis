<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\FrontendController;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Router\Router;
use Nemesis\Support\Api;
use Nemesis\Support\Form;
use Nemesis\Support\Table;
use Nemesis\Testing\TestCase;

class Phase1ResourceController
{
    public function index(Request $request): string
    {
        return 'index';
    }

    public function create(Request $request): string
    {
        return 'create';
    }

    public function store(Request $request): string
    {
        return 'store';
    }

    public function show(Request $request, string $id): string
    {
        return 'show:' . $id;
    }

    public function edit(Request $request, string $id): string
    {
        return 'edit:' . $id;
    }

    public function update(Request $request, string $id): string
    {
        return 'update:' . $id;
    }

    public function destroy(Request $request, string $id): string
    {
        return 'destroy:' . $id;
    }
}

class Phase1BuilderTest extends TestCase
{
    public function testFormMakeBuildsRenderableForms(): void
    {
        $html = (string) Form::make('/posts', 'PUT')
            ->attr('class', 'stacked-form')
            ->text('title', 'Hello')
            ->email('email', 'ada@example.com')
            ->password('password')
            ->checkbox('published', '1', true)
            ->select('status', ['draft' => 'Draft', 'live' => 'Live'], 'live')
            ->textarea('body', 'Body copy')
            ->submit('Save');

        $this->assertStringContainsString('<form method="POST" action="/posts" class="stacked-form">', $html);
        $this->assertStringContainsString('name="title"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('name="published"', $html);
        $this->assertStringContainsString('checked', $html);
        $this->assertStringContainsString('<option value="live" selected>', $html);
        $this->assertStringContainsString('<textarea name="body"', $html);
        $this->assertStringContainsString('<button type="submit"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
    }

    public function testTableMakeBuildsRenderableTables(): void
    {
        $html = (string) Table::make(['ID', 'Name'], [
            [1, 'Ada'],
            ['ID' => 2, 'Name' => 'Grace'],
        ])->attr('class', 'data-table');

        $this->assertStringContainsString('<table class="data-table">', $html);
        $this->assertStringContainsString('<th>ID</th>', $html);
        $this->assertStringContainsString('<th>Name</th>', $html);
        $this->assertStringContainsString('<td>Ada</td>', $html);
        $this->assertStringContainsString('<td>Grace</td>', $html);
    }

    public function testApiResourceRegistersConventionalRoutes(): void
    {
        $router = new Router();
        Router::setInstance($router);

        Api::resource('phase1-posts', Phase1ResourceController::class, $router);

        $this->assertSame('/phase1-posts', $router->generate('phase1-posts.index'));
        $this->assertSame('/phase1-posts/create', $router->generate('phase1-posts.create'));
        $this->assertSame('/phase1-posts/7', $router->generate('phase1-posts.show', ['id' => 7]));
        $this->assertSame('/phase1-posts/7/edit', $router->generate('phase1-posts.edit', ['id' => 7]));
        $this->assertSame('/phase1-posts/7', $router->generate('phase1-posts.update', ['id' => 7]));
        $this->assertSame('/phase1-posts/7', $router->generate('phase1-posts.destroy', ['id' => 7]));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/phase1-posts';
        $index = $router->dispatch('/phase1-posts', 'GET');
        $this->assertInstanceOf(Response::class, $index);
        $this->assertSame('index', $index->getContent());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/phase1-posts/7/edit';
        $edit = $router->dispatch('/phase1-posts/7/edit', 'GET');
        $this->assertInstanceOf(Response::class, $edit);
        $this->assertSame('edit:7', $edit->getContent());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/phase1-posts/7';
        $show = $router->dispatch('/phase1-posts/7', 'GET');
        $this->assertInstanceOf(Response::class, $show);
        $this->assertSame('show:7', $show->getContent());

        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REQUEST_URI'] = '/phase1-posts/7';
        $update = $router->dispatch('/phase1-posts/7', 'PUT');
        $this->assertInstanceOf(Response::class, $update);
        $this->assertSame('update:7', $update->getContent());

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_SERVER['REQUEST_URI'] = '/phase1-posts/7';
        $destroy = $router->dispatch('/phase1-posts/7', 'DELETE');
        $this->assertInstanceOf(Response::class, $destroy);
        $this->assertSame('destroy:7', $destroy->getContent());
    }
}
