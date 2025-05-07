@extends("layouts.app")

@section("content")
<div class="container">
    <h1>{{ $pageTitle ?? "Editar Configurações da Integração" }}</h1>

    @if(session("error"))
        <div class="alert alert-danger">
            {{ session("error") }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route("integration-settings.update", ["integration_setting" => 1]) }}" method="POST">
        @csrf
        @method("PUT") {{-- Resourceful controller expects PUT for update --}}

        <div class="card mb-3">
            <div class="card-header">
                Configurações Gerais da API VExpenses
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="VEXPENSES_REPORT_STATUS_TO_IMPORT" class="form-label">{{ $settings["VEXPENSES_REPORT_STATUS_TO_IMPORT"]->name ?? "Status de Relatório para Importar" }}</label>
                    <select class="form-select @error("VEXPENSES_REPORT_STATUS_TO_IMPORT") is-invalid @enderror" id="VEXPENSES_REPORT_STATUS_TO_IMPORT" name="VEXPENSES_REPORT_STATUS_TO_IMPORT">
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" {{ old("VEXPENSES_REPORT_STATUS_TO_IMPORT", $settings["VEXPENSES_REPORT_STATUS_TO_IMPORT"]->value ?? "APROVADO") == $value ? "selected" : "" }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error("VEXPENSES_REPORT_STATUS_TO_IMPORT")
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">{{ $settings["VEXPENSES_REPORT_STATUS_TO_IMPORT"]->description ?? "Status literal dos relatórios do VExpenses que devem ser importados." }}</div>
                </div>

                <div class="mb-3">
                    <label for="VEXPENSES_API_INCLUDES" class="form-label">{{ $settings["VEXPENSES_API_INCLUDES"]->name ?? "Dados Adicionais para Incluir (VExpenses API)" }}</label>
                    <input type="text" class="form-control @error("VEXPENSES_API_INCLUDES") is-invalid @enderror" 
                           id="VEXPENSES_API_INCLUDES" name="VEXPENSES_API_INCLUDES" 
                           value="{{ old("VEXPENSES_API_INCLUDES", $settings["VEXPENSES_API_INCLUDES"]->value ?? "users,expenses") }}">
                    @error("VEXPENSES_API_INCLUDES")
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">{{ $settings["VEXPENSES_API_INCLUDES"]->description ?? "Campos relacionados para incluir na consulta de relatórios, separados por vírgula (ex: users,expenses,projects)." }}</div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
        <a href="{{ route("integration-settings.index") }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection

