<?php

namespace App\Http\Controllers\Api;


use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;

use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ServerRequestInterface;
use App\Http\Controllers\Controller;

class TokenClientController extends Controller
{
    use HandlesOAuthErrors;

    /**
     * The authorization server.
     *
     * @var \League\OAuth2\Server\AuthorizationServer
     */
    protected $server;

    /**
     * The token repository instance.
     *
     * @var \Laravel\Passport\TokenRepository
     */
    protected $tokens;

    public function __construct(AuthorizationServer $server, TokenRepository $tokens)
    {
        $this->server = $server;
        $this->tokens = $tokens;
    }

    /**
     *
     *  @OA\Post(path="/api/token/client",
     *     tags={"Token Client"},
     *     description="Crear nuevo Token Client",
     *     summary="Crear nuevo cliente",
     *     operationId="tokenClient",
     *     @OA\Response(
     *         response="200",
     *         description="Token Client creado correctamente",
     *         @OA\JsonContent(
     *              type="object",
     *              ),
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Recurso no encontrado. La petición no devuelve ningún dato",
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Acceso denegado. No se cuenta con los privilegios suficientes",
     *         @OA\JsonContent(
     *              @OA\Property(property ="error",type="string",description="Mensaje de error de privilegios insuficientes")
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Error de Servidor.",
     *         @OA\JsonContent(
     *              @OA\Property(property ="error",type="string",description="Error de Servidor")
     *         )
     *     ),
     *
     *     @OA\RequestBody(
     *     description="Datos para  crear un nuevo token de cliente",
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *             type="object",
     *             
     *           
     *  @OA\Property(
     *                 property="client_id",
     *                 description="client_id",
     *                 type="string"
     *             ),
     *  @OA\Property(
     *                 property="client_secret",
     *                 description="client_secret",
     *               
     *                 type="string"
     *             ),

     *            
     *         )
     *     )
     *  )
     * )
     */

    public function issueToken(ServerRequestInterface $request)
    {
        return $this->withErrorHandling(function () use ($request) {
            return $this->convertResponse($this->server->respondToAccessTokenRequest($request, new Psr7Response()));
        });
    }
}