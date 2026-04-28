# Checklist de Execução - Compatibilização laravel-boleto

## Pré-requisitos

- [ ] Branch `feature/laravel-13-compat` criada a partir de `master`
- [ ] Projeto Laravel 13 rodando em `/home/ricardo/r2-projetos/r2-boletos/r2-boletos-render/new/` (Docker PHP 8.3)

## Execução

### Passo 1: Correção PHP 8.3
- [ ] Substituir `utf8_decode()` por `mb_convert_encoding()` em `src/Boleto/Render/AbstractPdf.php:113`
- [ ] Buscar outras ocorrências de `utf8_decode`/`utf8_encode` no src/ inteiro
- [ ] Se encontrar, corrigir todas

### Passo 2: Atualizar composer.json
- [ ] Expandir `"laravel/framework"` para `"^6.0||^7.0||^8.0||^9.0||^10.0||^11.0||^12.0||^13.0"`
- [ ] Expandir `"php"` para `"^7.4||^8.0||^8.1||^8.2||^8.3||^8.4||^8.5"`
- [ ] Atualizar `"require-dev"` para PHPUnit 9/10/11 e Orchestra Testbench 6-10
- [ ] Rodar `composer update` para validar que resolve sem conflito

### Passo 3: Corrigir Blade.php
- [ ] Linha 140: `new Dispatcher` → `new Dispatcher($this->container)`
- [ ] Verificar se outras assinaturas de construtores do Illuminate mudaram

### Passo 4: Testar no projeto Laravel 13
- [ ] No container: `composer require r2soft/laravel-boleto:dev-feature/laravel-13-compat`
- [ ] Rodar `./vendor/bin/phpunit` no laravel-boleto
- [ ] Validar que o render consegue usar as classes do pacote

### Passo 5: Commit e push
- [ ] `git add -A && git commit -m "compat: Laravel 6-13 support, PHP 8.3 fix"`
- [ ] `git push origin feature/laravel-13-compat`
- [ ] Após validação no render, merge para master e tag `00.010.017`
