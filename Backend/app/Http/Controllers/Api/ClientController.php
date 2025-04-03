<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;
use Laravel\Passport\ClientRepository;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Laravel\Passport\Http\Rules\RedirectRule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Admin\Empresa;
use App\Models\Token\EmpresaCliente;

class ClientController extends Controller
{
    use HandlesOAuthErrors;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    /**
     * The validation factory implementation.
     *
     * @var \Illuminate\Contracts\Validation\Factory
     */
    protected $validation;

    /**
     * The redirect validation rule.
     *
     * @var \Laravel\Passport\Http\Rules\RedirectRule
     */
    protected $redirectRule;

    /**
     * The client repository instance.
     *
     * @var \Laravel\Passport\ClientRepository
     */
    protected $clients;
    private $userInterface;
    private $personInterface;

    public function __construct(ClientRepository $clients, ValidationFactory $validation, RedirectRule $redirectRule)
    {

        $this->clients = $clients;
        $this->validation = $validation;
        $this->redirectRule = $redirectRule;
    }

    /**
     *
     *  @OA\Post(path="/client/save",
     *     tags={"Client"},
     *     description="Permite Crear nuevos clientes.",
     *     summary="Crear nuevo cliente",
     *     operationId="autenticar_updateId",
     *     @OA\Response(
     *         response="200",
     *         description="Se ha creado el cliente correctamente",
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
     *     description="Credenciales de ingreso",
     *     required=true,
     *     @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *             type="object",
     *             required ={"usuario","password"},
     *             @OA\Property(
     *                 property="name",
     *                 description="Nombre del cliente",
     *                 type="string"
     *             ),
     *
     *         )
     *     )
     *  )
     * )
     */

    public function storeCliente(Request $request)
    {
        $this->validation
            ->make($request->all(), [
                'name' => 'required|max:191',
                'redirect' => ['required', $this->redirectRule],
                'confidential' => 'boolean',
            ])
            ->validate();
        //verficar si ya existe el  $request->empresa_id
        $empresaCliente = EmpresaCliente::where('id_empresa', $request->empresa_id)->where('estado', 1)->first();

        if ($empresaCliente) {
            return response()->json(['error' => __('Ya existe un cliente para esta empresa')], 500);
        }

        $client = $this->clients->create($request->user()->getAuthIdentifier(), $request->name, $request->redirect, null, false, false, (bool) $request->input('confidential', true));

        if (Passport::$hashesClientSecrets) {
            return ['plainSecret' => $client->plainSecret] + $client->toArray();
        }

        $this->storeEmpresaCliente($client, $request->empresa_id);

        return $client->makeVisible('secret');
    }


    public function storeEmpresaCliente($cliente, $id_empresa)
    {
        $empresaCliente = new EmpresaCliente();
        $empresaCliente->id_empresa = $id_empresa;
        $empresaCliente->id_client = $cliente->id;
        $empresaCliente->id_user = $cliente->user_id;
        $empresaCliente->estado = 1;
        $empresaCliente->save();
        return $empresaCliente;
    }


    public function index()
    {
       

        $empresas = Empresa::all();
        $clientes = EmpresaCliente::with('cliente', 'empresa', 'cliente')->where('estado', 1)->get();
        // return response()->json($clientes);
        // return response()->json($empresas);
        return view('clients.index', compact('clientes', 'empresas'));
    }



    public function getClienteById(Request $request)
    {

        // return response()->json($request->all());
        $client = DB::table('oauth_clients')
            ->leftJoin('empresa_clientes', 'oauth_clients.id', '=', 'empresa_clientes.id_client')
            ->leftJoin('empresas', 'empresas.id', '=', 'empresa_clientes.id_empresa')
            ->select('oauth_clients.*', 'empresa_clientes.*', 'empresas.nombre as institucion', 'oauth_clients.secret')
            ->where('oauth_clients.id', $request->id)
            ->first();

        // Aquí puedes formatear $client según tus necesidades

        return response()->json($client);
    }


    public function destroy(Request $request)
    {
        $client = $this->clients->findForUser($request->clientId, $request->user()->getAuthIdentifier());

        if (!$client) {
            return response()->json(['error' => __('No se ha encontrado el cliente')], 500);
        }

        $this->clients->delete($client);

        $empresaCliente = EmpresaCliente::where('id_client', $request->clientId)->first();

        if (!$empresaCliente) {
            return response()->json(['error' => __('No se ha encontrado el cliente')], 500);
        }
        $empresaCliente->estado = 0;
        $empresaCliente->save();
        return response()->json(['message' => __('Se ha eliminado el cliente correctamente')]);
    }

}
