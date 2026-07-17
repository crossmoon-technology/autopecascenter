# autopecascenter

## Estrutura

### Base de Dados

#### `users`
Usuários do sistema.

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | bigint PK | |
| `role` | tinyint unsigned | Perfil de acesso — Enum: `1` Super Admin, `2` Admin, `3` Cliente |
| `name` | string | Nome completo |
| `email` | string unique | |
| `email_verified_at` | timestamp nullable | |
| `password` | string | Hash bcrypt |
| `document` | char(11) unique | CPF sem formatação |
| `remember_token` | string nullable | Token de sessão persistente |
| `deleted_at` | timestamp nullable | Soft delete |
| `created_at` / `updated_at` | timestamp | |

---

#### `password_reset_tokens`
Tokens para redefinição de senha.

| Coluna | Tipo | Descrição |
|---|---|---|
| `email` | string PK | |
| `token` | string | |
| `created_at` | timestamp nullable | |

---

#### `sessions`
Sessões ativas do Laravel.

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | string PK | |
| `user_id` | bigint FK nullable | Usuário autenticado na sessão |
| `ip_address` | string(45) nullable | |
| `user_agent` | text nullable | |
| `payload` | longtext | Dados serializados da sessão |
| `last_activity` | integer | Unix timestamp da última atividade |
