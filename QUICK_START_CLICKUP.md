# 🚀 Guia Rápido - Criar Tarefas no ClickUp

## ⚡ Configuração em 3 Passos

### Passo 1: Obter Token da API
1. Abra o ClickUp: https://app.clickup.com/settings/apps
2. Role até **"API Token"**
3. Clique em **"Generate"**
4. Copie o token (exemplo: `pk_12345678_ABCDEFGHIJKLMNOPQRSTUVWXYZ`)

### Passo 2: Configurar no WordPress
1. Vá em: **WP Admin → F2F Dashboard → ClickUp API**
2. Cole o token no campo **"API Token"**
3. Clique em **"Salvar Configurações"**
4. Aguarde verificar **"Conexão OK!"** ✅

### Passo 3: Selecionar Lista Padrão
1. Selecione seu **Workspace** no dropdown
2. Clique em **"Carregar Listas"**
3. Escolha a lista desejada
4. Clique em **"Usar Esta Lista"**
5. **Salve** as configurações

## ✨ Pronto! Agora você pode:

### 1️⃣ Criar Tarefas no Admin
Acesse: **WP Admin → F2F Dashboard → ClickUp API**

Preencha:
- ✅ Nome da tarefa
- 📝 Descrição
- 🎯 Prioridade
- 📅 Data de entrega
- 🏷️ Tags

Clique em **"Criar Tarefa"** e pronto!

### 2️⃣ Usar Shortcode em Páginas

Adicione em qualquer página ou post:

```
[f2f_create_task]
```

**Com opções customizadas:**
```
[f2f_create_task 
    list_id="901234567890" 
    button_text="Enviar Solicitação"
    show_priority="yes"
    show_due_date="yes"
    show_tags="no"]
```

### 3️⃣ Criar Tarefas via Código PHP

```php
<?php
$api = F2F_ClickUp_API::get_instance();

$tarefa = array(
    'name' => 'Minha tarefa',
    'description' => 'Descrição aqui',
    'priority' => 2, // 1=Urgente, 2=Alta, 3=Normal, 4=Baixa
    'tags' => array('importante', 'cliente-x')
);

$resultado = $api->create_task('ID_DA_LISTA', $tarefa);

if (!is_wp_error($resultado)) {
    echo 'Tarefa criada! ID: ' . $resultado['id'];
}
?>
```

## 🎯 Exemplos Práticos

### Formulário de Contato → Tarefa no ClickUp

```php
// Adicione no functions.php
add_action('wpcf7_mail_sent', 'criar_tarefa_contato');

function criar_tarefa_contato($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    $data = $submission->get_posted_data();
    
    $api = F2F_ClickUp_API::get_instance();
    $lista = get_option('f2f_clickup_default_list');
    
    $tarefa = array(
        'name' => 'Contato: ' . $data['your-name'],
        'description' => 'Email: ' . $data['your-email'] . 
                        "\n\nMensagem:\n" . $data['your-message'],
        'priority' => 2,
        'tags' => array('contato-site')
    );
    
    $api->create_task($lista, $tarefa);
}
```

### Botão "Reportar Bug"

Crie uma página com:
```
[f2f_create_task 
    button_text="Reportar Bug" 
    show_priority="yes"
    show_due_date="no"]
```

As tarefas criadas receberão automaticamente a tag `via-site`.

## 🔍 Onde encontrar o ID da Lista?

**Método 1: Na interface do admin**
1. Vá em **ClickUp API**
2. Selecione o workspace
3. Clique em **"Carregar Listas"**
4. Os IDs aparecem ao lado dos nomes

**Método 2: Na URL do ClickUp**
```
https://app.clickup.com/123456/v/l/901234567890
                                    ↑ Este é o ID da lista
```

## 🆘 Problemas Comuns

**❌ "ClickUp API não está configurada"**
→ Verifique se salvou o token corretamente

**❌ "401 Unauthorized"**
→ Token inválido. Gere um novo no ClickUp

**❌ "404 Not Found"**
→ ID da lista está incorreto

**❌ Tarefa não aparece no ClickUp**
→ Verifique se o workspace/lista estão corretos
→ Veja o console do navegador (F12) para erros

## 📚 Documentação Completa

Para mais detalhes, veja: `CLICKUP_INTEGRATION.md`

---

**🎉 Tudo funcionando? Comece a criar tarefas!**

