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
                    <label for="VEXPENSES_REPORT_STATUS_TO_IMPORT" class="form-label">{{ $settings["VEXPENSES_REPORT_STATUS_TO_IMPORT"]->label ?? ($settings["VEXPENSES_REPORT_STATUS_TO_IMPORT"]->name ?? "Status de Relatório para Importar") }}</label>
                    <select class="form-select @error("VEXPENSES_REPORT_STATUS_TO_IMPORT") is-invalid @enderror" id="VEXPENSES_REPORT_STATUS_TO_IMPORT" name="VEXPENSES_REPORT_STATUS_TO_IMPORT">
                        @foreach($statusOptions as $value => $label)
                            <option value="{{ $value }}" {{ (old("VEXPENSES_REPORT_STATUS_TO_IMPORT", $settings["VEXPENSES_REPORT_STATUS_TO_IMPORT"]->value ?? "APROVADO") == $value) ? "selected" : "" }}>
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
                    <label for="VEXPENSES_API_INCLUDES" class="form-label">{{ $settings["VEXPENSES_API_INCLUDES"]->label ?? ($settings["VEXPENSES_API_INCLUDES"]->name ?? "Dados Adicionais para Incluir (VExpenses API)") }}</label>
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

        <div class="card mb-3">
            <div class="card-header">
                Filtro de Período para Importação de Relatórios VExpenses
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="VEXPENSES_IMPORT_PERIOD_TYPE" class="form-label">{{ $settings['VEXPENSES_IMPORT_PERIOD_TYPE']->label ?? ($settings['VEXPENSES_IMPORT_PERIOD_TYPE']->name ?? 'Período de Importação') }}</label>
                    <select class="form-select @error("VEXPENSES_IMPORT_PERIOD_TYPE") is-invalid @enderror" id="VEXPENSES_IMPORT_PERIOD_TYPE" name="VEXPENSES_IMPORT_PERIOD_TYPE">
                        <option value="all_time" {{ (old("VEXPENSES_IMPORT_PERIOD_TYPE", $settings["VEXPENSES_IMPORT_PERIOD_TYPE"]->value ?? "all_time") == "all_time") ? "selected" : "" }}>Todo o período</option>
                        <option value="last_24_hours" {{ (old("VEXPENSES_IMPORT_PERIOD_TYPE", $settings["VEXPENSES_IMPORT_PERIOD_TYPE"]->value ?? "all_time") == "last_24_hours") ? "selected" : "" }}>Últimas 24 horas</option>
                        <option value="last_7_days" {{ (old("VEXPENSES_IMPORT_PERIOD_TYPE", $settings["VEXPENSES_IMPORT_PERIOD_TYPE"]->value ?? "all_time") == "last_7_days") ? "selected" : "" }}>Últimos 7 dias</option>
                        <option value="last_15_days" {{ (old("VEXPENSES_IMPORT_PERIOD_TYPE", $settings["VEXPENSES_IMPORT_PERIOD_TYPE"]->value ?? "all_time") == "last_15_days") ? "selected" : "" }}>Últimos 15 dias</option>
                        <option value="last_30_days" {{ (old("VEXPENSES_IMPORT_PERIOD_TYPE", $settings["VEXPENSES_IMPORT_PERIOD_TYPE"]->value ?? "all_time") == "last_30_days") ? "selected" : "" }}>Últimos 30 dias</option>
                        <option value="custom" {{ (old("VEXPENSES_IMPORT_PERIOD_TYPE", $settings["VEXPENSES_IMPORT_PERIOD_TYPE"]->value ?? "all_time") == "custom") ? "selected" : "" }}>Personalizado</option>
                    </select>
                    @error("VEXPENSES_IMPORT_PERIOD_TYPE")
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">{{ $settings['VEXPENSES_IMPORT_PERIOD_TYPE']->description ?? 'Selecione o período para buscar os relatórios do VExpenses. "Todo o período" não aplicará filtro de data.'}}</div>
                </div>

                <div id="custom_date_range_fields" style="{{ (old("VEXPENSES_IMPORT_PERIOD_TYPE", $settings["VEXPENSES_IMPORT_PERIOD_TYPE"]->value ?? "all_time") == "custom") ? "" : "display: none;" }}">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="VEXPENSES_IMPORT_CUSTOM_START_DATE" class="form-label">{{ $settings['VEXPENSES_IMPORT_CUSTOM_START_DATE']->label ?? ($settings['VEXPENSES_IMPORT_CUSTOM_START_DATE']->name ?? 'Data de Início (Personalizado)') }}</label>
                            <input type="date" class="form-control @error("VEXPENSES_IMPORT_CUSTOM_START_DATE") is-invalid @enderror" 
                                   id="VEXPENSES_IMPORT_CUSTOM_START_DATE" name="VEXPENSES_IMPORT_CUSTOM_START_DATE" 
                                   value="{{ old("VEXPENSES_IMPORT_CUSTOM_START_DATE", $settings["VEXPENSES_IMPORT_CUSTOM_START_DATE"]->value ?? "") }}">
                            @error("VEXPENSES_IMPORT_CUSTOM_START_DATE")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="VEXPENSES_IMPORT_CUSTOM_END_DATE" class="form-label">{{ $settings['VEXPENSES_IMPORT_CUSTOM_END_DATE']->label ?? ($settings['VEXPENSES_IMPORT_CUSTOM_END_DATE']->name ?? 'Data Final (Personalizado)') }}</label>
                            <input type="date" class="form-control @error("VEXPENSES_IMPORT_CUSTOM_END_DATE") is-invalid @enderror" 
                                   id="VEXPENSES_IMPORT_CUSTOM_END_DATE" name="VEXPENSES_IMPORT_CUSTOM_END_DATE" 
                                   value="{{ old("VEXPENSES_IMPORT_CUSTOM_END_DATE", $settings["VEXPENSES_IMPORT_CUSTOM_END_DATE"]->value ?? "") }}">
                            @error("VEXPENSES_IMPORT_CUSTOM_END_DATE")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="form-text">{{ $settings['VEXPENSES_IMPORT_CUSTOM_START_DATE']->description ?? 'Se "Personalizado" for selecionado, defina as datas de início e fim para a importação.'}}</div>
                </div>
            </div>
        </div>

        {{-- New Card for Debugging/Training Settings --}}
        <div class="card mb-3">
            <div class="card-header">
                Configurações de Depuração e Treinamento
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="form-check">
                        <input type="hidden" name="VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR" value="false"> {{-- Submits 'false' if checkbox is unchecked --}}
                        <input class="form-check-input @error('VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR') is-invalid @enderror" 
                               type="checkbox" 
                               value="true" {{-- Submits 'true' if checkbox is checked --}}
                               id="VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR" 
                               name="VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR"
                               {{ (old('VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR', $settings['VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR']->value ?? 'false') === 'true') ? 'checked' : '' }}
                               >
                        <label class="form-check-label" for="VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR">
                            {{ $settings['VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR']->label ?? ($settings['VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR']->name ?? 'Simular Erro de payment_date na API VExpenses') }}
                        </label>
                        @error('VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div class="form-text">{{ $settings['VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR']->description ?? 'Quando marcado, a aplicação não enviará o campo \'payment_date\' ao marcar um relatório como pago na VExpenses, simulando o erro \'Invalid data\'. Desmarcado, enviará o \'payment_date\' normalmente.' }}</div>
                    </div>
                </div>
            </div>
        </div>
        {{-- End New Card --}}

        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
        <a href="{{ route("integration-settings.index") }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const periodTypeSelect = document.getElementById("VEXPENSES_IMPORT_PERIOD_TYPE");
    const customDateFieldsDiv = document.getElementById("custom_date_range_fields");

    function toggleCustomDateFields() {
        if (periodTypeSelect.value === "custom") {
            customDateFieldsDiv.style.display = "block";
        } else {
            customDateFieldsDiv.style.display = "none";
        }
    }

    // Initial check
    toggleCustomDateFields();

    // Add event listener
    periodTypeSelect.addEventListener("change", toggleCustomDateFields);
});
</script>
@endsection

