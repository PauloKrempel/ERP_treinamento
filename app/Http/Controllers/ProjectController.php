<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $projects = Project::latest()->paginate(10);
        return view("projects.index", compact("projects"))
            ->with("pageTitle", "Projetos");
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view("projects.create")->with("pageTitle", "Adicionar Projeto");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            "name" => "required|string|max:255",
            "code" => "required|string|max:255|unique:projects,code",
            "description" => "nullable|string",
            "start_date" => "nullable|date",
            "end_date" => "nullable|date|after_or_equal:start_date",
        ]);

        Project::create($request->all());

        return redirect()->route("projects.index")
            ->with("success", "Projeto criado com sucesso.");
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        return view("projects.show", compact("project"))
            ->with("pageTitle", "Detalhes do Projeto");
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        return view("projects.edit", compact("project"))
            ->with("pageTitle", "Editar Projeto");
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $request->validate([
            "name" => "required|string|max:255",
            "code" => "required|string|max:255|unique:projects,code," . $project->id,
            "description" => "nullable|string",
            "start_date" => "nullable|date",
            "end_date" => "nullable|date|after_or_equal:start_date",
        ]);

        $project->update($request->all());

        return redirect()->route("projects.index")
            ->with("success", "Projeto atualizado com sucesso.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route("projects.index")
            ->with("success", "Projeto excluído com sucesso.");
    }
}

