(As respostas salvas são somente para visualização)
O documento a seguir é uma compilação e melhoria dos excertos fornecidos, estruturando as informações principais da API de Integração Taskrow para facilitar a leitura e o uso dos métodos disponíveis.

---

Taskrow | Manual de API de Integração
Versão 25.9.2 | API – 09/2025
Este manual descreve os principais métodos da API para integração de usuários, clientes, projetos e tarefas, bem como os detalhes de autenticação dos usuários.

1. Introdução
   A Taskrow localiza-se em R DA CONSOLAÇÃO 2302. A API é projetada para manipular entidades centrais como Usuários, Clientes, Projetos e Tarefas.
   A URL base para todas as chamadas é https://[HOST_NAME].taskrow.com/api/v1/ (ou v2, dependendo do método).
2. Chaves de Acesso e Autenticação
   2.1 Criando uma Chave de Acesso
   Toda chamada da API é realizada em nome de um usuário. Recomenda-se criar um usuário exclusivo para as integrações.
   Para criar a chave:
3. Crie o usuário normalmente, associando um e-mail.
4. Faça login na aplicação.
5. Na página de perfil, crie uma nova chave de API/Mobile.
   Esta chave é a ser incluída em cada requisição e deve ser mantida em segredo, como uma senha.
   2.2 Utilizando a Chave de Acesso
   A chave de acesso deve ser incluída como um item do cabeçalho das requisições.
   Item do Cabeçalho
   Exemplo de Valor
   \_\_identifier
   "YWohQCN2ZiBtMTIzamvnc2NRr...cWwjJGtxIUAjJEB3ZW5jaW8xMjMzbG5zZA=="

---

3. MÉTODOS: Usuários (Users)
   Usuários são definidos como todos os colaboradores, terceiros e freelancers que utilizam o Taskrow.
   3.1 Listar Usuários
   Lista todos os usuários cadastrados.
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/User/ListUsers
   Método
   GET
   Exemplo de Retorno JSON: O retorno é uma lista de objetos, incluindo UserID, FullName, MainEmail, UserLogin, ProfileTitle, ApprovalGroup, entre outros.
   [
   {
   "UserID": 123,
   "FullName": "Ricardo Santos Silva",
   "MainEmail": "ricardo.silva@empresa.com.br",
   "ProfileTitle": "Equipe",
   "FunctionGroupName": "Produto"
   //... demais propriedades
   }
   ] [2, 6]
   3.2 Incluir um Novo Usuário
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/User/SaveNewUser
   Método
   POST
   Parâmetros Principais (Campos Obrigatórios são marcados com _):
   Campo
   Detalhe
   Exemplo de Uso
   AppMainCompanyID _
   ID da empresa no taskrow
   99999
   AppMainLanguageID _
   Idioma do usuário (fixo)
   1
   FullName _
   Nome completo (string até 100 caracteres)
   "Nome completo"
   UserLogin _
   Nome para exibição (string até 60 caracteres)
   "Nome de exibição"
   ProfileID _
   Id do perfil de acesso (determina permissões)
   99
   DateStart \*
   Data de início no sistema
   31/12/2015 (formato dd/MM/aaaa)
   ExternalCode
   Código externo para futuras integrações
   "XXXX"
   Exemplo de Retorno JSON (Sucesso):
   {
   Success: true/false,
   Message: "Msg de erro",
   Entity: {
   UserID: 9999 // ID do usuário criado
   }
   } [9]
   3.3 Obter Detalhes de um Usuário (por Código Externo)
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/User/GetUserByExternalCode
   Método
   GET
   Parâmetro de Query: externalCode
   3.4 Edição de Dados do Usuário
   A edição de dados (contato, adicionais, administrativos) utiliza o mesmo endpoint, mas requer a especificação da Section.
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/User/SaveUser
   Método
   POST
   Seção 1: Dados de Contato (Section: Contact)
   Campo
   Detalhe
   UserID
   99999
   Section
   Contact
   UserDetail.HomeAddressNu<br>mber
   Número (aceita letras e números)
   Seção 2: Informações Adicionais (Section: Detail)
   Campo
   Detalhe
   Exemplo de Uso
   UserID
   99999
   Section
   Detail
   UserDetail.CivilStatus
   Códigos: 1 - Solteiro(a), 2 - Casado(a), ..., 6 - Viúvo(a)
   2
   UserDetail.UserScholarityID
   Grau de formação (1 a 12)
   7 (Ensino Superior)
   UserDetail.DateBirth
   Data de nascimento
   dd/mm/yyyy
   UserDetail.EthnicityID
   O ID de uma das etnias cadastradas na empresa
   99
   Seção 3: Dados Administrativos (Section: administrative)
   Campo
   Detalhe
   UserID
   99999
   Section
   administrative
   UserAdministrativeDetail.PIS<br>Pasep
   Número do PIS-PASEP
   UserAdministrativeDetail.Va<br>cationPeriodDateStart
   Data inicial do controle de férias
   3.5 Listar Etnias Cadastradas
   Permite buscar as etnias disponíveis para uso no campo UserDetail.EthnicityID.
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Administrative/ListCompanyEthnicity
   Método
   GET
   Exemplo de Retorno JSON:
   {
   "EthnicityList": [
   {
   "EthnicityID": 99,
   "Name": "XXXX"
   }
   ]
   } [18]

---

4. MÉTODOS: Clientes (Clients)
   Clientes englobam clientes, prospects, fornecedores e a própria empresa (para projetos internos). Todo item no Taskrow é relacionado a um cliente.
   4.1 Criar Clientes
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Client/SaveClient
   Método
   POST
   Parâmetros Principais (Campos Obrigatórios são marcados com _):
   Campo
   Detalhe
   ClientID
   Fixo, sempre 0
   ClientName _
   Nome do cliente (string até 80 caracteres)
   CountryID: \*
   Fixo 31 (Brasil)
   OwnerID:
   UserID do usuário responsável pelo Cliente
   ExternalCode:
   Código de sistema externo para integrações (máximo 40 caracteres)
   OBSERVAÇÃO: Campos opcionais (²) se preenchidos, exigem o preenchimento de todos os outros campos opcionais relacionados ao endereço.
   Exemplo de Retorno JSON (Sucesso):
   {
   Success: true/false,
   Entity: {
   ClientID: 9999,
   ClientNickname: 'XXXXXXX' // Nome da 'pasta' do cliente (necessário para outras requisições)
   }
   } [22]
   4.2 Criar Endereço / CNPJ
   Todo cliente pode ter um ou mais endereços, sendo que cada endereço possui um CNPJ.
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Client/SaveClientAddress
   Método
   POST
   Parâmetros Principais:
   Campo
   Detalhe
   Exemplo de Uso
   ClientID
   ID do cliente retornado por SaveClient
   9999
   ClientNickName
   Nome da 'pasta' do cliente retornado por SaveClient
   "XXXXX"
   ClientAddressID
   Fixo 0
   0
   CNPJ
   00.000.000/0001-01
   CityID
   ID da Cidade (ver Listar Cidades)
   7352
   ZipCode
   CEP
   "01311-200"
   4.3 Obter Detalhes do Cliente (por Código Externo)
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Client/GetClientByExternalCode
   Método
   GET
   Parâmetro de Query: externalCode
   Exemplo de Retorno JSON:
   {
   Client: {
   ClientID: 99999,
   ClientNickname: “XXXXXXX”, // Pasta do cliente
   //... demais campos
   }
   } [24]

---

5. MÉTODOS: Projetos (Jobs)
   Projetos são a maior unidade de trabalho e podem representar projetos tradicionais, contas recorrentes ou atividades internas.
   5.1 Listar Projetos (v2)
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v2/core/job/list
   Método
   GET
   Parâmetros (Query String - Opcionais):
   Parâmetro
   Detalhe
   includeInactives
   true / false
   orderBy
   Title / TitleDesc / Number / NumberDesc
   nextToken
   Token de continuação para paginação
   ClientID
   ID do cliente
   5.2 Criar Projetos
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Job/SaveJob
   Método
   POST
   Parâmetros Principais (Campos Obrigatórios são marcados com _):
   Campo
   Detalhe
   Exemplo de Uso
   ClientID _
   Id do cliente
   9999
   JobTitle _
   Título do job
   "Job do Natal"
   JobTypeID _
   Tipo do job: 1 - Atividade Geral, 2 - Valor fixo, etc.
   1
   OwnerUserID _
   ID do usuário responsável pelo job
   999
   clientNickName _
   Nome da pasta do cliente (retornado na criação do cliente)
   "NomeDaPastaDoCliente"
   TagListString
   Lista de tags para classificação (Não requer cadastro anterior)
   Produto1,Online
   ExternalCode
   Código externo
   99999
   Exemplo de Retorno JSON (Sucesso):
   {
   Success: true/false,
   Entity: {
   JobID: 9999,
   JobNumber: 99999 // Número sequencial do projeto (requisitado em futuras chamadas)
   }
   } [28]
   5.3 Mudar Status de um Projeto
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Job/UpdateJobStatus
   Método
   GET
   Parâmetros (Query String):
   Campo
   Detalhe
   Status ID
   clientNickName
   Nome da pasta do cliente
   jobNumber
   Número do projeto
   status
   4 = bloqueado, 3 = liberado
   4 ou 3

---

6. MÉTODOS: Tarefas (Tasks)
   Tarefas são o "coração do taskrow", representando algo a ser realizado com um ciclo de vida curto.
   6.1 Criar uma Nova Tarefa
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Task/SaveTask
   Método
   POST
   Parâmetros Principais:
   Campo
   Detalhe
   DueDate
   Prazo (data em formato canônico, sem suporte a horas diferentes de zero)
   JobID
   ID do job em que a tarefa será criada
   OwnerUserID
   ID do usuário responsável pela tarefa
   TaskItemComment
   Texto inicial da tarefa (briefing). Suporta tags HTML como font, p, div, b, em
   TaskTitle
   Título da tarefa
   jobNumber
   Número do job (retornado na criação do job)
   ExternalCode
   Código externo da tarefa
   Exemplo de Retorno JSON (Sucesso):
   {
   Success: true/false,
   Entity: {
   TaskID: 9999, // ID da tarefa
   TaskNumber: 99999 // Número sequencial da tarefa
   }
   } [33]
   6.2 Obter Detalhes da Tarefa (por Número)
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Task/TaskDetail
   Método
   GET
   Parâmetros (Query String): clientNickname, jobNumber, taskNumber, connectionID
   6.3 Salvar Subtarefa (Item de Checklist)
   Cria um item de checklist dentro de uma tarefa existente.
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Task/SaveSubtask
   Método
   POST
   Parâmetros Principais:
   Campo
   Detalhe
   subtask.TaskID
   ID da tarefa principal
   subtask.SubtaskID
   Fixo 0
   subtask.Title
   Título da subtarefa
   subtask.Done
   true ou false
   6.4 Salvar Subtarefa (Tarefa Subordinada)
   Cria uma nova tarefa subordinada a uma tarefa principal, utilizando o endpoint SaveTask.
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Task/SaveTask
   Método
   POST
   Parâmetros Adicionais (além dos campos padrão de criação de tarefa):
   Campo
   Detalhe
   TaskID
   ID da tarefa principal
   TaskNumber
   Número da tarefa principal
   RowVersion
   Versão atualizada da tarefa principal
   createChildTask
   Fixo true
   Title
   Título da nova tarefa subordinada

---

7. MÉTODOS: Timesheet
   Para obter o máximo proveito do Taskrow, é crucial que todas as atividades tenham suas horas apontadas.
   7.1 Horas Lançadas
   Obtém o tempo gasto em minutos em uma tarefa específica.
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Task/GetTaskMinutesSpent
   Método
   GET
   Parâmetro de Query: taskNumber (Número sequencial da tarefa).
   Exemplo de Retorno JSON:
   O retorno inclui a lista de lançamentos (MainTask), detalhando o User (com UserID, FullName e ExternalCode) e o TotalMinutesSpent (em inteiro) para cada usuário.

---

8. MÉTODOS: Buscas
   8.1 Busca de Clientes
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Search/SearchClients
   Método
   GET
   Parâmetros (Query String):
   Campo
   Detalhe
   q
   Nome do cliente / CNPJ no formato 09.999.999/0000-00
   showInactives
   true / false (opcional)
   8.2 Busca de Projetos
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Search/SearchJobs
   Método
   GET
   Parâmetros (Query String):
   Campo
   Detalhe
   clientID
   ID de um cliente (opcional)
   q
   Nome do projeto / Nome do cliente / Tag do projeto
   Nota sobre Tags: Para buscar por tag, forneça o prefixo #. Exemplo: para a tag "segundo semestre", use q=#segundo+semestre.
   8.3 Busca de Tarefas (Busca Avançada)
   Esta busca utiliza o método POST para permitir filtros complexos.
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v2/search/tasks/advancedsearch
   Método
   POST
   Parâmetros (Body JSON):
   Todos os campos são opcionais. Para uma busca sem parâmetros, envie um objeto vazio no corpo.
   Campo
   Detalhe
   ClientID, JobID
   ID do cliente ou projeto para filtrar
   Term
   Termo de busca (pode ser parcial). Use # para buscar por tag
   Closed
   true (tarefas fechadas), false (abertas) ou null (todas)
   StartDate, EndDate
   Período de busca
   Offset
   Valor para paginação. O retorno indica o NextOffset
   Sort
   Critério de ordenação (DueDateDesc ou Title)
   Exemplo de Requisição (Body JSON):
   {
   "ClientID": 123,
   "JobID": 456,
   "Term": "tarefa importante",
   "StartDate": "2025-09-01T00:00:00Z",
   "FilterUserID": 789,
   "Offset": 0,
   "Closed": null
   } [45]

---

9. MÉTODOS: Cadastros Auxiliares
   9.1 Listar Cidades
   Permite listar cidades por UF.
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/Client/ListCities
   Método
   GET
   Parâmetro (Query String): uf (Exemplo: SP)
   9.2 Listar Subtipos de Projeto
   Detalhe
   Valor
   URL
   https://[HOST_NAME].taskrow.com/api/v1/ Administrative/ListJobSubType
   Método
   GET
   Exemplo de Retorno JSON: Lista de JobSubTypeList contendo JobSubTypeID e Name.
