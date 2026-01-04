# Pasta de Deploy (Produção) - Guia Rápido

Este diretório contém instruções para preparar e transferir o projeto ComissionamentoV2 para produção.

## O que o script faz

- Copia o projeto para uma pasta `*_DEPLOY` (por defeito `cleanwattsportal_DEPLOY`).
- Exclui ficheiros e pastas de desenvolvimento (.git, node_modules, documentation, tests, etc.).
- Copia `config/DATABASE.DEPLOY.php` para `config/production.php` na pasta de deploy, se o ficheiro existir.
- Cria `config/.production` na pasta de deploy (flag que ativa o modo produção em `config/database.php`).

## Como usar (PowerShell)

1. Abrir PowerShell no directório do projeto (por ex: `c:\xampp\htdocs\cleanwattsportal`).
2. Executar:

```powershell
.\DEPLOY_PREPARE_PRODUCTION.ps1
```

3. O script irá criar a pasta de destino (por defeito `cleanwattsportal_DEPLOY`).
4. Abrir a pasta `cleanwattsportal_DEPLOY` e verificar:
   - que `config/production.php` existe (com as credenciais)
   - que `config/.production` existe
   - que ficheiros de desenvolvimento/documentação foram removidos
5. Abrir FileZilla e transferir todo o conteúdo da pasta `cleanwattsportal_DEPLOY` para a pasta `htdocs` do servidor de produção.

## Atenção e segurança

- **Remover** credenciais sensíveis do repositório após o deploy, se forem inseridas aqui. O ideal é definir variáveis de ambiente na infra de hospedagem.
- Não executar `setup_database.php` em produção sem confirmar — pode modificar o esquema da BD.
- Confirma que `config/production.php` contém as credenciais corretas antes da transferência.

## Verificar deploy no servidor

- Depois de transferir, acede à `https://<domain>/` e testa as funcionalidades básicas:
  - Login
  - Abrir um relatório
  - Guardar um relatório
  - Gerar PDF (se aplicável — depende de dompdf ou configuração no servidor)

## Passos opcionais

- Instalar dependências com Composer (se o servidor não tiver Dompdf instalado)
  - `composer require dompdf/dompdf:1.2.*`
- (Opcional) instalar Node e Puppeteer se usares o renderer headless para PDFs (offline ou em build pipeline)

## Suporte

Se precisares, posso:

- Gerar a pasta agora e confirmar os ficheiros nela;
- Transferir via FTP se tiveres credenciais (não guardar senhas no repositório).
