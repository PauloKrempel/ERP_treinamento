<?php

namespace App\Http\Controllers;

use App\Models\CostCenter;
use Illuminate\Http\Request;

class CostCenterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $costCenters = CostCenter::latest()->paginate(10);
        return view('cost_centers.index', compact('costCenters'))
            ->with('pageTitle', 'Centros de Custo');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('cost_centers.create')->with('pageTitle', 'Adicionar Centro de Custo');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:cost_centers,code',
            'description' => 'nullable|string',
        ]);

        CostCenter::create($request->all());

        return redirect()->route('cost_centers.index')
            ->with('success', 'Centro de Custo criado com sucesso.');
    }

    /**
     * Display the specified resource.
     */
    public function show(CostCenter $costCenter)
    {
        return view('cost_centers.show', compact('costCenter'))
            ->with('pageTitle', 'Detalhes do Centro de Custo');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CostCenter $costCenter)
    {
        return view('cost_centers.edit', compact('costCenter'))
            ->with('pageTitle', 'Editar Centro de Custo');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CostCenter $costCenter)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:cost_centers,code,' . $costCenter->id,
            'description' => 'nullable|string',
        ]);

        $costCenter->update($request->all());

        return redirect()->route('cost_centers.index')
            ->with('success', 'Centro de Custo atualizado com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CostCenter $costCenter)
    {
        $costCenter->delete();

        return redirect()->route('cost_centers.index')
            ->with('success', 'Centro de Custo excluído com sucesso.');
    }
}

