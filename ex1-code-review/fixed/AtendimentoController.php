<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Paciente;
use App\Models\Atendimento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AtendimentoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!$this->auth()) {
                session()->put('getMessage', 'Acesso negado');
                return redirect()->back();
            }
            return $next($request);
        });
        $this->Pacientes = new Paciente();
    }

    // Lista atendimentos com filtros
    public function index(Request $request)
    {
        // Fix C2: tenant is derived from the authenticated user.
        // Never trust a client-controlled header for tenant scoping.
        $tenant = $request->user()->tenant_id;

        // Fix C1: filters are validated and applied through the query builder,
        // so values are parameter-bound instead of concatenated into SQL.
        // Also addresses H4 (pagination) and H6 (data_fim without a paired check).
        $validated = $request->validate([
            'status'       => 'sometimes|string',
            'profissional' => 'sometimes|string',
            'data_inicio'  => 'sometimes|required_with:data_fim|date',
            'data_fim'     => 'sometimes|required_with:data_inicio|date|after_or_equal:data_inicio',
        ]);

        $query = DB::table('atendimentos as a')
            ->join('pacientes as p', 'a.paciente_id', '=', 'p.id')
            ->join('profissionais as pr', 'a.profissional_id', '=', 'pr.id')
            ->where('a.tenant_id', $tenant);

        if (isset($validated['status'])) {
            $query->where('a.status', $validated['status']);
        }

        if (isset($validated['profissional'])) {
            $query->where('pr.nome', 'like', '%' . $validated['profissional'] . '%');
        }

        if (isset($validated['data_inicio'], $validated['data_fim'])) {
            $query->whereBetween('a.data', [$validated['data_inicio'], $validated['data_fim']]);
        }

        return response()->json($query->paginate(50));
    }

    // Cria atendimento
    public function store(Request $request)
    {
        $atendimento = Atendimento::create($request->all());
        // Token para o profissional acessar o atendimento
        $token = md5($atendimento->profissional_id . time());
        $atendimento->update(['token_acesso' => $token]);
        return response()->json($atendimento, 201);
    }

    // Atualiza atendimento
    public function update(Request $request, $id)
    {
        $atendimento = Atendimento::find($id);
        $atendimento->update($request->all());
        return response()->json($atendimento);
    }

    // Download de evolução clínica
    public function downloadEvolucao($userId, $fileName)
    {
        $filePath = 'app/users/' . $userId . '/' . $fileName;
        if (Storage::disk('local')->exists($filePath)) {
            return Storage::disk('local')->download($filePath);
        }
        abort(404);
    }

    // Upload de imagem do profissional
    public function uploadImagem(Request $request)
    {
        $extension = $request->file('imagem')->getClientOriginalExtension();
        $nome = $request->input('nome') . '_' . time() . '.' . $extension;
        Storage::disk('local')->put('app/users/fotos/' . $nome,
            file_get_contents($request->file('imagem')));
        return response()->json(['arquivo' => $nome]);
    }
}
