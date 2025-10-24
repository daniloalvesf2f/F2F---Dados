# 📋 Guia de Integração com ClickUp

## 🚀 Configuração Inicial

### 1. Obter API Token do ClickUp

1. Acesse: https://app.clickup.com/settings/apps
2. Clique em **"Generate"** na seção **API Token**
3. Copie o token gerado (começa com `pk_`)

### 2. Configurar no WordPress

1. Acesse: **WP Admin > F2F Dashboard > ClickUp API**
2. Cole o **API Token** no campo correspondente
3. Clique em **"Salvar Configurações"**
4. Se a conexão for bem-sucedida, verá a mensagem: **"Conexão OK!"**

### 3. Configurar Workspace e Lista Padrão

1. Selecione o **Workspace Padrão** no dropdown
2. Clique em **"Carregar Listas"** para ver todas as listas disponíveis
3. Selecione a lista desejada
4. Clique em **"Usar Esta Lista"**
5. Salve as configurações

---

## ✨ Funcionalidades Disponíveis

### 📝 Criar Tarefas pelo Admin

Na página **ClickUp API**, após configurar, você pode criar tarefas diretamente:

**Campos disponíveis:**
- ✅ Nome da Tarefa (obrigatório)
- 📄 Descrição
- 📋 Lista (usa padrão se vazio)
- 🎯 Prioridade (Urgente, Alta, Normal, Baixa)
- 📅 Data de Entrega
- 🏷️ Tags (separadas por vírgula)

**Exemplo:**
```
Nome: Implementar novo recurso
Descrição: Adicionar sistema de notificações
Prioridade: Alta
Tags: desenvolvimento, sprint-3, frontend
```

---

## 🔧 Uso Programático

### Criar Tarefa via PHP

```php
<?php
// Obter instância da API
$api = F2F_ClickUp_API::get_instance();

// Dados da tarefa
$task_data = array(
    'name' => 'Minha Nova Tarefa',
    'description' => 'Descrição detalhada da tarefa',
    'priority' => 2, // 1=Urgente, 2=Alta, 3=Normal, 4=Baixa
    'due_date' => '2025-12-31 23:59:59',
    'tags' => array('importante', 'urgente'),
    'assignees' => array(123456), // IDs dos usuários
);

// Criar tarefa
$list_id = '901234567890'; // ID da lista
$result = $api->create_task($list_id, $task_data);

if (is_wp_error($result)) {
    echo 'Erro: ' . $result->get_error_message();
} else {
    echo 'Tarefa criada com sucesso! ID: ' . $result['id'];
    echo 'URL: ' . $result['url'];
}
?>
```

### Buscar Tarefas

```php
<?php
$api = F2F_ClickUp_API::get_instance();

$list_id = '901234567890';
$filters = array(
    'archived' => false,
    'page' => 0,
    'order_by' => 'created',
    'reverse' => true,
    'include_closed' => false,
);

$tasks = $api->search_tasks($list_id, $filters);

if (!is_wp_error($tasks)) {
    foreach ($tasks as $task) {
        echo $task['name'] . '<br>';
    }
}
?>
```

### Atualizar Tarefa

```php
<?php
$api = F2F_ClickUp_API::get_instance();

$task_id = '123abc';
$updates = array(
    'name' => 'Novo nome da tarefa',
    'status' => 'in progress',
    'priority' => 1,
);

$result = $api->update_task($task_id, $updates);
?>
```

### Adicionar Comentário

```php
<?php
$api = F2F_ClickUp_API::get_instance();

$task_id = '123abc';
$comment = 'Este é um comentário importante!';

$result = $api->add_comment($task_id, $comment);
?>
```

---

## 🎨 Shortcode para Frontend

Você pode usar o shortcode `[f2f_create_task]` em qualquer página ou post:

```
[f2f_create_task list_id="901234567890"]
```

**Parâmetros opcionais:**
- `list_id` - ID da lista (usa padrão se não informado)
- `button_text` - Texto do botão (padrão: "Criar Tarefa")
- `show_priority` - Mostrar campo de prioridade (padrão: yes)
- `show_due_date` - Mostrar data de entrega (padrão: yes)
- `show_tags` - Mostrar campo de tags (padrão: yes)

**Exemplo completo:**
```
[f2f_create_task 
    list_id="901234567890" 
    button_text="Enviar Solicitação"
    show_priority="no"
    show_tags="no"]
```

---

## 🔍 Métodos Disponíveis da API

### Workspaces e Estrutura

```php
// Listar workspaces (teams)
$workspaces = $api->get_workspaces();

// Listar spaces de um workspace
$spaces = $api->get_spaces($team_id);

// Listar listas de um space
$lists = $api->get_lists($space_id);

// Obter membros do workspace
$members = $api->get_team_members($team_id);
```

### Tarefas

```php
// Criar tarefa
$task = $api->create_task($list_id, $task_data);

// Obter tarefa
$task = $api->get_task($task_id);

// Atualizar tarefa
$result = $api->update_task($task_id, $updates);

// Deletar tarefa
$result = $api->delete_task($task_id);

// Buscar tarefas
$tasks = $api->search_tasks($list_id, $filters);
```

### Status e Custom Fields

```php
// Obter status de uma lista
$statuses = $api->get_list_statuses($list_id);

// Obter custom fields
$fields = $api->get_custom_fields($list_id);

// Definir custom field
$result = $api->set_custom_field($task_id, $field_id, $value);
```

### Anexos e Comentários

```php
// Adicionar comentário
$comment = $api->add_comment($task_id, 'Meu comentário');

// Upload de arquivo
$result = $api->upload_attachment($task_id, '/path/to/file.pdf');
```

---

## 🔐 Segurança

### Permissões

- Apenas usuários com capacidade `manage_options` podem:
  - Configurar a API
  - Criar tarefas via admin
  - Acessar endpoints AJAX

### Validações

- ✅ Nonces em todas as requisições AJAX
- ✅ Sanitização de todos os inputs
- ✅ Verificação de permissões
- ✅ Tratamento de erros da API

---

## 🐛 Troubleshooting

### Erro: "ClickUp API não está configurada"

**Solução:** Verifique se o API Token foi salvo corretamente em **ClickUp API > Configuração da API**

### Erro: "401 Unauthorized"

**Solução:** O token está inválido ou expirou. Gere um novo token no ClickUp.

### Erro: "404 Not Found"

**Solução:** Verifique se o ID da lista está correto. Use o botão "Carregar Listas" para ver todas as listas disponíveis.

### Erro: "Rate limit exceeded"

**Solução:** Você excedeu o limite de requisições. A API do ClickUp tem limites de:
- 100 requisições por minuto (padrão)
- 1000 requisições por minuto (enterprise)

### Timeout nas requisições

**Solução:** Aumente o timeout em `class-clickup-api.php`:

```php
'timeout' => 60, // Aumentar para 60 segundos
```

---

## 📊 Limites da API ClickUp

| Plano | Requisições/minuto | Requisições/hora |
|-------|-------------------|------------------|
| Free | 100 | 6,000 |
| Unlimited | 100 | 6,000 |
| Business | 100 | 6,000 |
| Enterprise | 1,000 | 60,000 |

---

## 🔄 Sincronização Automática

Para sincronizar tarefas criadas via site com o dashboard:

1. As tarefas criadas aparecerão instantaneamente no ClickUp
2. Para importá-las no dashboard local:
   - Vá em **F2F Dashboard > Configurações**
   - Clique em **"Buscar do Google Sheets"** ou **"Importar CSV"**
   - Ou configure uma sincronização automática via cron

---

## 💡 Dicas e Boas Práticas

### 1. Use Tags para Organização

```php
$task_data['tags'] = array('frontend', 'urgente', 'cliente-x');
```

### 2. Configure Prioridades Corretamente

- **1 = Urgente** (vermelho): Para emergências
- **2 = Alta** (amarelo): Importante e urgente
- **3 = Normal** (azul): Tarefas regulares
- **4 = Baixa** (cinza): Baixa prioridade

### 3. Use Datas no Formato Correto

```php
// ✅ Correto
'due_date' => '2025-12-31 23:59:59'

// ❌ Incorreto
'due_date' => '31/12/2025'
```

### 4. Trate Erros Adequadamente

```php
$result = $api->create_task($list_id, $task_data);

if (is_wp_error($result)) {
    error_log('Erro ao criar tarefa: ' . $result->get_error_message());
    // Notificar usuário ou tentar novamente
} else {
    // Sucesso!
}
```

---

## 📝 Exemplos de Uso

### Criar Tarefa de Formulário de Contato

```php
add_action('wpcf7_mail_sent', 'create_task_from_contact_form');

function create_task_from_contact_form($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    $posted_data = $submission->get_posted_data();
    
    $api = F2F_ClickUp_API::get_instance();
    $list_id = get_option('f2f_clickup_default_list');
    
    $task_data = array(
        'name' => 'Novo contato: ' . $posted_data['your-name'],
        'description' => 'Email: ' . $posted_data['your-email'] . "\n\n" . 
                        'Mensagem: ' . $posted_data['your-message'],
        'priority' => 2,
        'tags' => array('contato', 'site'),
    );
    
    $api->create_task($list_id, $task_data);
}
```

### Widget de Criação Rápida

```php
class F2F_Quick_Task_Widget extends WP_Widget {
    public function widget($args, $instance) {
        echo $args['before_widget'];
        echo '<form class="f2f-quick-task">';
        echo '<input type="text" name="task_name" placeholder="Nova tarefa..." required>';
        echo '<button type="submit">Criar</button>';
        echo '</form>';
        echo $args['after_widget'];
    }
}
```

---

## 🆘 Suporte

Para mais informações sobre a API do ClickUp:
- 📚 [Documentação Oficial](https://clickup.com/api)
- 💬 [Community](https://help.clickup.com/)
- 🐛 [Report Issues](https://github.com/clickup/api-docs/issues)

---

**Desenvolvido por F2F Marketing** 🚀

