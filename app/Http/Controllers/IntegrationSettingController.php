<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class IntegrationSettingController extends Controller
{
    /**
     * Display a listing of the integration settings.
     */
    public function index()
    {
        $this->ensureDefaultSettingsExist();
        $settings = IntegrationSetting::all()->keyBy("key");
        return view("integration_settings.index", compact("settings"))
            ->with("pageTitle", "Configurações da Integração VExpenses");
    }

    /**
     * Show the form for editing the integration settings.
     */
    public function edit()
    {
        $this->ensureDefaultSettingsExist();
        $settings = IntegrationSetting::all()->keyBy("key");
        $statusOptions = [
            "ABERTO" => "ABERTO",
            "ENVIADO" => "ENVIADO",
            "APROVADO" => "APROVADO",
            "PAGO" => "PAGO",
            "REPROVADO" => "REPROVADO",
        ];
        return view("integration_settings.edit", compact("settings", "statusOptions"))
            ->with("pageTitle", "Editar Configurações da Integração");
    }

    /**
     * Update the specified integration settings in storage.
     */
    public function update(Request $request)
    {
        $this->ensureDefaultSettingsExist();
        
        $rules = [
            "VEXPENSES_REPORT_STATUS_TO_IMPORT" => "required|string|in:ABERTO,APROVADO,REPROVADO,REABERTO,PAGO,ENVIADO",
            "VEXPENSES_API_INCLUDES" => "nullable|string",
            "VEXPENSES_IMPORT_PERIOD_TYPE" => ["required", "string", Rule::in(["all_time", "last_24_hours", "last_7_days", "last_15_days", "last_30_days", "custom"])],
            "VEXPENSES_IMPORT_CUSTOM_START_DATE" => "nullable|date|required_if:VEXPENSES_IMPORT_PERIOD_TYPE,custom",
            "VEXPENSES_IMPORT_CUSTOM_END_DATE" => "nullable|date|required_if:VEXPENSES_IMPORT_PERIOD_TYPE,custom|after_or_equal:VEXPENSES_IMPORT_CUSTOM_START_DATE",
            "VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR" => "required|in:true,false", // Added rule for the new checkbox
        ];

        $validatedData = $request->validate($rules);

        try {
            foreach ($validatedData as $key => $value) {
                if (in_array($key, ["VEXPENSES_IMPORT_CUSTOM_START_DATE", "VEXPENSES_IMPORT_CUSTOM_END_DATE"]) && $validatedData["VEXPENSES_IMPORT_PERIOD_TYPE"] !== "custom") {
                    $valueToSave = null;
                } else {
                    $valueToSave = $value;
                }
                IntegrationSetting::updateOrCreate(
                    ["key" => $key],
                    ["value" => $valueToSave]
                );
            }
            
            if ($validatedData["VEXPENSES_IMPORT_PERIOD_TYPE"] !== "custom") {
                IntegrationSetting::updateOrCreate(["key" => "VEXPENSES_IMPORT_CUSTOM_START_DATE"], ["value" => null]);
                IntegrationSetting::updateOrCreate(["key" => "VEXPENSES_IMPORT_CUSTOM_END_DATE"], ["value" => null]);
            }

            // Explicitly handle VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR as it might not be in $validatedData if unchecked and no hidden input is used correctly
            // The blade already includes a hidden input with value="false", so $request->VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR will be 'true' or 'false'
            // The validation rule 'required|in:true,false' ensures it's present and has one of these string values.
            // So, the loop above already handles it correctly.

            return redirect()->route("integration-settings.index")
                ->with("success", "Configurações da integração atualizadas com sucesso.");
        } catch (\Exception $e) {
            Log::error("Error updating integration settings: " . $e->getMessage(), ["exception" => $e]);
            return redirect()->back()
                ->with("error", "Erro ao atualizar as configurações: " . $e->getMessage());
        }
    }

    /**
     * Ensures default settings exist in the database.
     */
    private function ensureDefaultSettingsExist()
    {
        $defaults = [
            [
                "key" => "VEXPENSES_REPORT_STATUS_TO_IMPORT",
                "value" => "APROVADO",
                "name" => "Status de Relatório VExpenses para Importar",
                "description" => "Status literal dos relatórios do VExpenses que devem ser importados (ex: ABERTO,APROVADO,REPROVADO,REABERTO,PAGO,ENVIADO). Padrão: Aprovado."
            ],
            [
                "key" => "VEXPENSES_API_INCLUDES",
                "value" => "users,expenses",
                "name" => "Dados Adicionais para Incluir (VExpenses API)",
                "description" => "Campos relacionados para incluir na consulta de relatórios da API VExpenses, separados por vírgula (ex: users,expenses,projects,cost_centers). Padrão: users,expenses."
            ],
            [
                "key" => "VEXPENSES_IMPORT_PERIOD_TYPE",
                "value" => "all_time",
                "name" => "Período de Importação de Relatórios VExpenses",
                "description" => "Define o período padrão para buscar relatórios do VExpenses (ex: all_time, last_24_hours, custom)."
            ],
            [
                "key" => "VEXPENSES_IMPORT_CUSTOM_START_DATE",
                "value" => null,
                "name" => "Data de Início Personalizada para Importação",
                "description" => "Se o período de importação for \'custom\', esta é a data de início."
            ],
            [
                "key" => "VEXPENSES_IMPORT_CUSTOM_END_DATE",
                "value" => null,
                "name" => "Data Final Personalizada para Importação",
                "description" => "Se o período de importação for \'custom\', esta é a data final."
            ],
            [
                "key" => "VEXPENSES_SIMULATE_PAYMENT_DATE_ERROR", // Added default for the new setting
                "value" => "false", // Default to 'false' (string, as values are stored as strings)
                "name" => "Simular Erro de payment_date na API VExpenses",
                "description" => "Quando marcado, a aplicação não enviará o campo \'payment_date\' ao marcar um relatório como pago na VExpenses, simulando o erro \'Invalid data\'. Desmarcado, enviará o \'payment_date\' normalmente."
            ],
        ];

        foreach ($defaults as $setting) {
            IntegrationSetting::firstOrCreate(["key" => $setting["key"]], $setting);
        }
    }
}

