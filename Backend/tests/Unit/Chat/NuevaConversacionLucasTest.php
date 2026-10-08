<?php

namespace Tests\Unit\Chat;

use App\Http\Controllers\Api\Chat\ChatController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NuevaConversacionLucasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'lucas.base_url' => 'http://lucas.test',
            'lucas.api_key' => '',
            'lucas.timeout' => 5,
        ]);
    }

    public function test_bandera_new_conversation_crea_el_hilo_y_el_chat_lo_usa(): void
    {
        Http::fake([
            'http://lucas.test/conversations/new*' => Http::response([
                'conversation_id' => 'conv-nueva',
                'title' => 'Cuanto vendimos',
            ]),
            'http://lucas.test/chat' => Http::response([
                'message' => '<p>Listo</p>',
                'suggestions' => [],
                'conversation_id' => 'conv-nueva',
            ]),
        ]);

        $response = app(ChatController::class)->chat($this->request([
            'message' => 'cuanto vendimos',
            'new_conversation' => true,
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('conv-nueva', $response->getData(true)['conversation_id']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && str_contains($request->url(), '/conversations/new')
                && str_contains($request->url(), 'user_id=7')
                && str_contains($request->url(), 'empresa_id=3')
                && str_contains(urldecode($request->url()), 'Cuanto vendimos');
        });

        Http::assertSent(function ($request) {
            return str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/chat')
                && ($request->data()['conversation_id'] ?? null) === 'conv-nueva';
        });
    }

    public function test_sin_bandera_no_abre_otro_hilo(): void
    {
        Http::fake([
            'http://lucas.test/chat' => Http::response([
                'message' => '<p>Sigo</p>',
                'suggestions' => [],
                'conversation_id' => 'conv-vieja',
            ]),
        ]);

        $response = app(ChatController::class)->chat($this->request([
            'message' => 'sigue',
            'conversation_id' => 'conv-vieja',
        ]));

        $this->assertSame('conv-vieja', $response->getData(true)['conversation_id']);
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/conversations/new');
        });
        Http::assertSent(function ($request) {
            return ($request->data()['conversation_id'] ?? null) === 'conv-vieja';
        });
    }

    private function request(array $body): Request
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 7;
        $user->id_empresa = 3;
        $user->shouldReceive('tipoParaLucas')->andReturn('Usuario');

        $request = Request::create('/api/chat', 'POST', $body);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
