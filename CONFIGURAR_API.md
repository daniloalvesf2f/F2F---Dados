# 🔑 Configurar API do ClickUp

## Token Fornecido
```
pk_230408565_HMN6TL7ZJNBVOWYRK3PV3IX298ICQHZI
```

## 📋 Passo a Passo Rápido

### 1️⃣ Acessar Configurações
Vá em: **WP Admin → F2F Dashboard → ClickUp API**

URL direta:
```
seu-site.com/wp-admin/admin.php?page=f2f-clickup-api
```

### 2️⃣ Inserir o Token
1. Cole o token no campo **"API Token"**:
   ```
   pk_230408565_HMN6TL7ZJNBVOWYRK3PV3IX298ICQHZI
   ```
2. Clique em **"Salvar Configurações"**
3. Aguarde a mensagem: **"Conexão OK!"** ✅

### 3️⃣ Configurar Workspace e Lista
1. Após salvar, a página recarrega e mostra os workspaces disponíveis
2. Selecione o **Workspace** no dropdown
3. Clique em **"Carregar Listas"**
4. Selecione a lista desejada
5. Clique em **"Usar Esta Lista"**
6. Clique em **"Salvar Configurações"** novamente

### 4️⃣ Acessar Página de Responsáveis
Após configurar, acesse:
```
seu-site.com/responsaveis-clickup/
```

Ou use o menu de navegação (item será adicionado automaticamente para administradores).

---

## 🎯 O que a Página de Responsáveis Mostra

### Cards Individuais com:
- 👤 **Nome** do responsável
- 📧 **Email**
- 🖼️ **Foto de perfil** (ou iniciais coloridas)
- ⏱️ **Horas totais trackadas** (formato: XXh XXm)
- 📋 **Número de tarefas**
- 📊 **Barra de progresso** (contribuição percentual)

### Estatísticas Gerais (topo):
- 👥 **Total de membros** da equipe
- ⏰ **Horas totais** de toda a equipe
- ✅ **Tarefas totais** registradas
- 📈 **Média de horas** por pessoa

---

## 🔄 Como Funciona

1. **Busca membros do workspace** via API do ClickUp
2. **Busca dados de tempo** do banco de dados local (importado via CSV)
3. **Combina os dados** relacionando pelo nome do responsável
4. **Calcula totais** e percentuais
5. **Exibe cards ordenados** do maior para o menor tempo

---

## ⚠️ Importante

- Os **dados de tempo** vêm do banco de dados local
- Você precisa ter **importado dados do CSV** antes
- Se não houver dados, os cards mostrarão **0h 00m**
- Para ter dados atualizados:
  - Vá em **F2F Dashboard → Configurações**
  - Importe o CSV mais recente
  - Ou configure importação automática

---

## 🎨 Visual

- **Design moderno** com cards animados
- **Cores personalizadas** para cada responsável
- **Totalmente responsivo** (desktop, tablet, mobile)
- **Animações suaves** ao carregar
- **Hover effects** nos cards

---

## 🚀 Acesso Rápido

Após configurar, você pode:
- ✅ Criar tarefa: `seu-site.com/criar-tarefa-clickup/`
- ✅ Ver responsáveis: `seu-site.com/responsaveis-clickup/`
- ✅ Dashboard: `seu-site.com/`

---

## 💡 Próximos Passos

1. ✅ Configure a API com o token acima
2. ✅ Importe dados do CSV (se ainda não fez)
3. ✅ Acesse a página de responsáveis
4. 🎉 Veja as métricas da sua equipe!

