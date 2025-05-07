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

        $httpClient = Http::withHeaders([
            "Authorization" => $this->apiToken,
            "Accept" => "application/json",
            "Content-Type" => "application/json",
        ]);

        if (app()->isLocal()) {
            $httpClient = $httpClient->withoutVerifying();
        }

        // Modificado para usar $data para métodos não-GET e $processedQueryParams para GET
        $response = null;
        if (strtolower($method) === "get") {
            $response = $httpClient->{$method}($url, $processedQueryParams);
        } else {
            $response = $httpClient->{$method}($url, $data);
        }

        if ($response->failed()) {
            Log::error("Falha na Requisição API VExpenses", [
                "url" => $url,
                "method" => $method,
                "status" => $response->status(),
                "response" => $response->body(),
                "data_sent" => $data, // Loga os dados enviados para métodos não-GET
                "query_params" => $processedQueryParams, // Loga os query params para GET
            ]);
            // Retornar um array com informações do erro para melhor tratamento no controller
            return [
                "success" => false,
                "status" => $response->status(),
                "message" => $response->json("message", "Erro desconhecido na API VExpenses."),
                "errors" => $response->json("data.errors", $response->json("errors", [])),
                "response_body" => $response->body()
            ];
        }
        
        $jsonResponse = $response->json();
        // Adicionar 'success' => true para respostas bem-sucedidas para consistência
        if (is_array($jsonResponse)) {
            $jsonResponse['success'] = true;
        } else {
            // Se a resposta não for um array (ex: string vazia em sucesso 204), crie um array
            $jsonResponse = ['success' => true, 'data' => $jsonResponse];
        }
        return $jsonResponse;
    }


    public function getReports(array $filters = [], $includes = null)
    {
        $queryParams = [];
        $endpoint = "reports";

        if (isset($filters["status_string"]) && !empty($filters["status_string"])) {
            $statusString = $filters["status_string"];
            $endpoint = "reports/status/" . rawurlencode($statusString);
            unset($filters["status_string"]);
        } elseif (isset($filters["status_id"])) {
            $queryParams["status_id"] = $filters["status_id"];
            unset($filters["status_id"]);
        }

        foreach ($filters as $key => $value) {
            if (!empty($value)) {
                $queryParams[$key] = $value;
            }
        }

        if (!empty($includes)) {
            $queryParams["include"] = is_array($includes) ? implode(",", $includes) : $includes;
        }

        return $this->makeRequest("get", $endpoint, [], $queryParams);
    }

    // Modificado para aceitar $data como segundo parâmetro
    public function markReportAsPaid(string $reportId, array $data = [])
    {
        $endpoint = "reports/{$reportId}/pay";
        // Passa $data para makeRequest, que será usado no corpo da requisição PUT
        return $this->makeRequest("put", $endpoint, $data);
    }

    public function updateReportStatus(string $reportId, int $statusId)
    {
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

