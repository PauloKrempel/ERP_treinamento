<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VExpensesService
{
    protected $apiToken;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiToken = config("services.vexpenses.api_token");
        $this->baseUrl = config("services.vexpenses.base_url");

        if (empty($this->apiToken) || empty($this->baseUrl)) {
            Log::error("VExpenses API token ou URL base não configurados.");
        }
    }

    protected function makeRequest(string $method, string $endpoint, array $data = [], array $queryParams = [])
    {
        if (empty($this->apiToken) || empty($this->baseUrl)) {
            return null;
        }

        $url = rtrim($this->baseUrl, "/") . "/" . ltrim($endpoint, "/");

        $processedQueryParams = [];
        foreach ($queryParams as $key => $value) {
            if (is_array($value)) {
                $processedQueryParams[$key] = $value;
            } else {
                $processedQueryParams[$key] = $value;
            }
        }

        // 1. Configura o cliente HTTP base
        $httpClient = Http::withHeaders([
            "Authorization" => $this->apiToken, // CORRETO: Sem "Bearer "
            "Accept" => "application/json",
            "Content-Type" => "application/json",
        ]);

        // 2. Adiciona a opção para desabilitar a verificação SSL em ambiente local
        if (app()->isLocal()) { // Verifica se o ambiente é local
            $httpClient = $httpClient->withoutVerifying();
        }

        // 3. Executa a requisição USANDO o $httpClient configurado
        $response = $httpClient->{$method}($url, $method === "get" ? $processedQueryParams : $data);


        if ($response->failed()) {
            Log::error("Falha na Requisição API VExpenses", [
                "url" => $url,
                "method" => $method,
                "status" => $response->status(),
                "response" => $response->body(),
                "data_sent" => $data,
                "query_params" => $processedQueryParams,
            ]);
            return null;
        }

        return $response->json();
    }


    /**
     * Get reports from VExpenses API.
     *
     * @param array $filters (e.g., ["status_string" => "Aprovado", "start_date" => "2023-01-01", "end_date" => "2023-01-31"])
     * @param array|string|null $includes (e.g., ["users", "expenses"] or "users,expenses")
     * @return array|null
     */
    public function getReports(array $filters = [], $includes = null)
    {
        $queryParams = [];
        $endpoint = "reports"; // Default endpoint

        // Handle status filter: if status_string is provided, use the specific endpoint
        if (isset($filters["status_string"]) && !empty($filters["status_string"])) {
            $statusString = $filters["status_string"];
            $endpoint = "reports/status/" . rawurlencode($statusString); // Use the specific endpoint for status string
            unset($filters["status_string"]); // Remove from queryParams as it's in the path
        } elseif (isset($filters["status_id"])) {
            // Fallback or alternative: if status_id is used, add to queryParams for the general /reports endpoint
            $queryParams["status_id"] = $filters["status_id"];
            unset($filters["status_id"]);
        }

        // Add other filters (like dates) to queryParams
        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $queryParams[$key] = $value;
            }
        }

        // Handle includes
        if (!empty($includes)) {
            $queryParams["include"] = is_array($includes) ? implode(",", $includes) : $includes;
        }

        return $this->makeRequest("get", $endpoint, [], $queryParams);
    }

    public function markReportAsPaid(string $reportId)
    {
        $endpoint = "reports/{$reportId}/pay";
        return $this->makeRequest("put", $endpoint, []);
    }

    public function updateReportStatus(string $reportId, int $statusId) // This might need to change if status is always string
    {
        // This method might be less used if we primarily filter by status string
        // and pay reports. If direct status updates with numeric IDs are still needed,
        // it can remain. Otherwise, it might need adaptation or removal.
        $endpoint = "reports/{$reportId}/status";
        $data = ["status_id" => $statusId];
        return $this->makeRequest("put", $endpoint, $data);
    }

    public function getMember(string $memberId, $includes = null)
    {
        $queryParams = [];
        if (!empty($includes)) {
            $queryParams["include"] = is_array($includes) ? implode(",", $includes) : $includes;
        }
        $endpoint = "members/{$memberId}";
        return $this->makeRequest("get", $endpoint, [], $queryParams);
    }

    public function createMember(array $memberData)
    {
        return $this->makeRequest("post", "members", $memberData);
    }
}
