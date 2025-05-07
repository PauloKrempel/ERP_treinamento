<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IntegrationSettingController extends Controller
{
    /**
     * Display a listing of the integration settings.
     */
    public function index()
    {
        $this->ensureDefaultSettingsExist();
        $settings = IntegrationSetting::all();
        return view("integration_settings.index", compact("settings"))
            ->with("pageTitle", "Configurações da Integração VExpenses");
    }

    /**
     * Show the form for editing the integration settings.
     */
    public function edit(IntegrationSetting $integrationSetting) 
    {
        $this->ensureDefaultSettingsExist();
        $settings = IntegrationSetting::all()->keyBy("key");
        // Provide status options for the edit view
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
        ];

        $validatedData = $request->validate($rules);

        try {
            foreach ($validatedData as $key => $value) {
                IntegrationSetting::updateOrCreate(
                    ["key" => $key],
                    ["value" => $value]
                );
            }
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
                "value" => "APROVADO", // Default to string "Aprovado"
                "name" => "Status de Relatório VExpenses para Importar",
                "description" => "Status literal dos relatórios do VExpenses que devem ser importados (ex: ABERTO,APROVADO,REPROVADO,REABERTO,PAGO,ENVIADO). Padrão: Aprovado."
            ],
            [
                "key" => "VEXPENSES_API_INCLUDES",
                "value" => "users,expenses", // Default includes
                "name" => "Dados Adicionais para Incluir (VExpenses API)",
                "description" => "Campos relacionados para incluir na consulta de relatórios da API VExpenses, separados por vírgula (ex: users,expenses,projects,cost_centers). Padrão: users,expenses."
            ],
        ];

        foreach ($defaults as $setting) {
            IntegrationSetting::firstOrCreate(["key" => $setting["key"]], $setting);
        }
    }
}

