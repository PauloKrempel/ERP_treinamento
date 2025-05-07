<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::latest()->paginate(10);
        return view("users.index", compact("users"))
            ->with("pageTitle", "Usuários");
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view("users.create")->with("pageTitle", "Adicionar Usuário");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            "name" => "required|string|max:255",
            "email" => "required|string|email|max:255|unique:users,email",
            "password" => "required|string|min:8|confirmed",
            "vexpenses_id" => "nullable|string|max:255|unique:users,vexpenses_id",
        ]);

        User::create([
            "name" => $request->name,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "vexpenses_id" => $request->vexpenses_id,
        ]);

        return redirect()->route("users.index")
            ->with("success", "Usuário criado com sucesso.");
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return view("users.show", compact("user"))
            ->with("pageTitle", "Detalhes do Usuário");
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        return view("users.edit", compact("user"))
            ->with("pageTitle", "Editar Usuário");
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            "name" => "required|string|max:255",
            "email" => "required|string|email|max:255|unique:users,email," . $user->id,
            "password" => "nullable|string|min:8|confirmed",
            "vexpenses_id" => "nullable|string|max:255|unique:users,vexpenses_id," . $user->id,
        ]);

        $data = $request->only("name", "email", "vexpenses_id");
        if ($request->filled("password")) {
            $data["password"] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route("users.index")
            ->with("success", "Usuário atualizado com sucesso.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route("users.index")
            ->with("success", "Usuário excluído com sucesso.");
    }
}

