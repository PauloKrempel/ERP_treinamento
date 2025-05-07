# Projeto ERP de Integração VExpenses com Laravel

Este projeto demonstra a integração de um sistema ERP desenvolvido em Laravel com a API do VExpenses. Ele permite o cadastro de Centros de Custo, Projetos e Usuários. As funcionalidades centrais incluem uma aba de "Contas a Pagar" que lista relatórios financeiros (importados do VExpenses ou lançados manualmente), uma aba de "Configuração da Integração" para definir parâmetros da API VExpenses (como o **status literal** dos relatórios a serem importados e campos a serem incluídos na consulta), e a sincronização de pagamentos com a API do VExpenses.

## Funcionalidades Principais

-   **Gestão Local:**
    -   Cadastro, Visualização, Edição e Exclusão (CRUD) de Centros de Custo.
    -   Cadastro, Visualização, Edição e Exclusão (CRUD) de Projetos.
    -   Cadastro, Visualização, Edição e Exclusão (CRUD) de Usuários (com campo para ID de integração VExpenses, essencial para associar relatórios importados).
-   **Contas a Pagar (Relatórios Financeiros Locais):**
    -   Listagem de todos os relatórios financeiros armazenados localmente (importados do VExpenses ou criados manualmente).
    -   Filtros por status local, origem (VExpenses/Manual), e período.
    -   Botão para "Puxar Relatórios do VExpenses" que importa dados da API VExpenses para a base local, evitando duplicidade, utilizando o status configurado.
    -   Formulário para "Adicionar Relatório Manual" diretamente no ERP.
    -   Funcionalidade de "Marcar como Pago" para relatórios:
        -   Atualiza o status do relatório para "Pago" localmente.
        -   Se o relatório for originário do VExpenses, envia uma requisição para a API do VExpenses (usando `PUT /v2/reports/{id}/pay`) para marcar como pago lá também.
-   **Configuração da Integração VExpenses:**
    -   Interface para definir parâmetros da API VExpenses, como:
        -   "Status de Relatório VExpenses para Importar" (deve ser a **string literal** do status, ex: "Aprovado", "Pendente").
        -   "Dados Adicionais para Incluir" (parâmetro `include` da API, ex: `users,expenses,projects`).
    -   Essas configurações são usadas dinamicamente ao puxar relatórios do VExpenses.
-   **(Opcional) Consulta Direta à API VExpenses:**
    -   Uma rota (`/vexpenses-reports-direct`) ainda permite consultar diretamente a API do VExpenses (funcionalidade original), útil para testes e comparações.

## Pré-requisitos para Ambiente Local

-   PHP >= 8.1
-   Composer
-   Servidor Web (Apache, Nginx) ou capacidade de usar o servidor embutido do PHP (`php artisan serve`)
-   Extensões PHP: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML, SQLite3 (ou o driver do banco de dados de sua escolha, como `php-mysql`)
-   Node.js e NPM (para compilação de assets, se for modificar o frontend com Vite)
-   Um token de API válido do VExpenses.

## Instruções de Instalação e Configuração

1.  **Clone ou Baixe o Projeto:**
    *   Descompacte o arquivo `erp-vexpenses-laravel_no_vendor.zip` em um diretório de sua escolha.

2.  **Navegue até o Diretório do Projeto:**
    ```bash
    cd caminho/para/erp-vexpenses-laravel
    ```

3.  **Instale as Dependências do Composer:**
    ```bash
    composer install
    ```

4.  **Copie o Arquivo de Ambiente:**
    ```bash
    cp .env.example .env
    ```

5.  **Gere a Chave da Aplicação Laravel:**
    ```bash
    php artisan key:generate
    ```

6.  **Configure o Banco de Dados no Arquivo `.env`:**
    *   O projeto está configurado por padrão para usar SQLite. Um arquivo `database/database.sqlite` será criado automaticamente ao rodar as migrations se não existir.
    *   Para usar SQLite (padrão):
        ```env
        DB_CONNECTION=sqlite
        # DB_HOST=127.0.0.1
        # DB_PORT=3306
        # DB_DATABASE=laravel
        # DB_USERNAME=root
        # DB_PASSWORD=
        ```
        (Opcional) Se o SQLite não criar o arquivo automaticamente, você pode especificar o caminho absoluto:
        `DB_DATABASE=/caminho/completo/para/seu/erp-vexpenses-laravel/database/database.sqlite`

    *   Para usar MySQL (exemplo):
        ```env
        DB_CONNECTION=mysql
        DB_HOST=127.0.0.1
        DB_PORT=3306
        DB_DATABASE=seu_banco_de_dados_mysql
        DB_USERNAME=seu_usuario_mysql
        DB_PASSWORD=sua_senha_mysql
        ```
        Se usar MySQL, crie o banco de dados no seu servidor MySQL antes de rodar as migrations.

7.  **Configure as Credenciais da API VExpenses no Arquivo `.env`:**
    Adicione as seguintes linhas ao seu arquivo `.env`, substituindo `SEU_TOKEN_VEXPENSES_AQUI` pelo seu token real:
    ```env
    VEXPENSES_API_TOKEN=SEU_TOKEN_VEXPENSES_AQUI
    VEXPENSES_BASE_URL=https://api.vexpenses.com/v2
    ```

8.  **Execute as Migrations do Banco de Dados:**
    Isso criará todas as tabelas necessárias, incluindo `financial_reports` e `integration_settings`.
    ```bash
    php artisan migrate
    ```
    (Se encontrar problemas com migrations antigas ou quiser recomeçar, use `php artisan migrate:fresh`)

9.  **(Opcional) Compile os Assets Frontend (se houver modificações):**
    ```bash
    npm install
    npm run dev # ou npm run build para produção
    ```
    Este projeto utiliza Bootstrap via CDN no layout principal, então este passo pode não ser estritamente necessário para rodar inicialmente.

10. **Inicie o Servidor de Desenvolvimento Laravel:**
    ```bash
    php artisan serve
    ```
    Por padrão, a aplicação estará acessível em `http://localhost:8000`.

11. **Acesse a Aplicação:**
    Abra seu navegador e acesse `http://localhost:8000`.

## Uso

-   **Página Inicial:** Redireciona para a aba "Contas a Pagar" (`/financial-reports`).
-   **Contas a Pagar (`/financial-reports`):**
    -   Visualize relatórios financeiros locais.
    -   Use o botão "Puxar Relatórios do VExpenses" para importar dados da API (respeitando as configurações da integração).
    -   Use o botão "Adicionar Relatório Manual" para criar entradas diretamente no ERP.
    -   Marque relatórios como "Pagos".
-   **Configuração da Integração (`/integration-settings`):**
    -   Acesse para visualizar e editar os parâmetros de integração com a API VExpenses. Certifique-se de que o "Status de Relatório VExpenses para Importar" seja a **string literal exata** que a API espera (ex: "Aprovado", "Pendente", "Pago", "Rejeitado", "Aberto").
-   **Cadastros Básicos:**
    -   Centros de Custo: `/cost_centers`
    -   Projetos: `/projects`
    -   Usuários: `/users` (lembre-se de configurar o "ID VExpenses" para correta associação dos relatórios importados).

## Estrutura do Projeto (Principais Diretórios Adicionais/Modificados)

-   `app/Http/Controllers/FinancialReportController.php`: Controller para a aba "Contas a Pagar".
-   `app/Http/Controllers/IntegrationSettingController.php`: Controller para a aba "Configuração da Integração".
-   `app/Http/Controllers/ReportController.php`: Agora contém a lógica de importação e a funcionalidade de marcar como pago (que interage com `FinancialReportController`).
-   `app/Models/FinancialReport.php`: Modelo Eloquent para os relatórios financeiros locais.
-   `app/Models/IntegrationSetting.php`: Modelo Eloquent para as configurações da integração.
-   `app/Services/VExpensesService.php`: Serviço para interagir com a API do VExpenses, atualizado para usar parâmetros dinâmicos e o endpoint de status com string literal.
-   `database/migrations/`: Contém as novas migrations para `financial_reports` e `integration_settings`.
-   `resources/views/financial_reports/`: Views para a aba "Contas a Pagar".
-   `resources/views/integration_settings/`: Views para a aba "Configuração da Integração" (com dropdown para seleção de status literal).
-   `routes/web.php`: Rotas atualizadas para as novas funcionalidades.

## Observações

-   A autenticação de usuários não foi implementada neste projeto para simplificar o escopo da demonstração. Laravel Breeze ou Jetstream podem ser adicionados para essa funcionalidade.
-   O tratamento de erros na comunicação com a API VExpenses é feito com logs. Em um ambiente de produção, um sistema de notificação ou tratamento mais sofisticado seria recomendado.
-   Ao puxar relatórios do VExpenses, a lógica de prevenção de duplicidade é baseada no `vexpenses_report_id`.

