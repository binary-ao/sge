<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogActividade;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogActividadeController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
        ]);

        $logs = LogActividade::with('utilizador', 'escola')
            ->when($request->filled('utilizador_id'), fn ($q) => $q->where('utilizador_id', $request->integer('utilizador_id')))
            ->when($request->filled('accao'), fn ($q) => $q->where('accao', $request->accao))
            ->when($request->filled('q'), fn ($q) => $q->where('descricao', 'like', '%'.$request->q.'%'))
            ->when($request->filled('de'), fn ($q) => $q->whereDate('created_at', '>=', $request->de))
            ->when($request->filled('ate'), fn ($q) => $q->whereDate('created_at', '<=', $request->ate))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.logs.index', [
            'logs' => $logs,
            'utilizadores' => User::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function show(LogActividade $log): View
    {
        return view('admin.logs.show', ['log' => $log->load('utilizador.perfil', 'escola')]);
    }
}
