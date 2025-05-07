@extends("layouts.app")

@section("content")
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>{{ $pageTitle ?? "Configurações da Integração VExpenses" }}</h1>
        <a href="{{ route("integration-settings.edit", ["integration_setting" => 1]) }}" class="btn btn-primary">Editar Configurações</a>
        {{-- We pass a dummy ID like 1 because resourceful routes for edit expect an ID, but we edit all at once --}}
    </div>

    @if(session("success"))
        <div class="alert alert-success">
            {{ session("success") }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            Configurações Atuais
        </div>
        <ul class="list-group list-group-flush">
            @forelse ($settings as $setting)
                <li class="list-group-item">
                    <h5>{{ $setting->name }}</h5>
                    <p class="mb-1"><strong>Chave:</strong> <code>{{ $setting->key }}</code></p>
                    <p class="mb-1"><strong>Valor:</strong> <code>{{ $setting->value }}</code></p>
                    <p class="text-muted">{{ $setting->description }}</p>
                </li>
            @empty
                <li class="list-group-item">Nenhuma configuração encontrada.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection

