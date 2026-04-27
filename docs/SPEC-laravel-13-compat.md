# Compatibilização do laravel-boleto com Laravel 13 (PHP 8.3)

## Contexto

O `r2-boletos-render` está sendo migrado de Lumen 5.6 para Laravel 13.
O `r2soft/laravel-boleto` é a dependência crítica que precisa funcionar no novo framework.

**Branch**: `feature/laravel-13-compat`
**Repo**: `/home/ricardo/r2-projetos/r2-boletos/laravel-boleto/`
**Remote**: `https://github.com/r2soft/laravel-boleto`

---

## O que o pacote faz

Biblioteca para geração e processamento de boletos bancários brasileiros:

- **Boleto/**: Classes de domínio para 13 bancos (Sicredi, Itau, BB, Bradesco, Santander, Caixa, Bancoob/Sicoob, Banrisul, BRB, BNB, HSBC, Unicred, QISCD)
- **Boleto/Render/**: Geração de PDF e HTML de boletos e carnês
- **Cnab/Remessa/**: Geração de arquivos CNAB 240/400 para remessa ao banco
- **Cnab/Retorno/**: Parseamento de arquivos CNAB 240/400 de retorno do banco
- **CalculoDV.php**: Cálculo de dígitos verificadores
- **Pessoa.php**: DTO para pagador/beneficiário
- **Util.php**: Helpers (formatação, validação, manipulação de strings e arquivos)

---

## composer.json atual

```json
{
    "require": {
        "php": ">7.4",
        "ext-intl": "*",
        "ext-mbstring": "*",
        "laravel/framework": "^5.0||^6.0",
        "neitanod/forceutf8": "^2.0",
        "setasign/fpdf": "^1.8"
    }
}
```

---

## Tarefas a executar

### TAREFA 1: Corrigir `utf8_decode()` deprecated (P0 - Obrigatório)

**Arquivo**: `src/Boleto/Render/AbstractPdf.php`, linha 113

**Problema**: `utf8_decode()` foi deprecated no PHP 8.2 e será removido no PHP 9.0.

**Antes**:
```php
protected function _()
{
    $args = func_get_args();
    $var  = utf8_decode(array_shift($args));
    $s    = vsprintf($var, $args);
    return $s;
}
```

**Depois**:
```php
protected function _()
{
    $args = func_get_args();
    $var  = mb_convert_encoding(array_shift($args), 'ISO-8859-1', 'UTF-8');
    $s    = vsprintf($var, $args);
    return $s;
}
```

**Validação**: Buscar por outras ocorrências de `utf8_decode` ou `utf8_encode` no src/ inteiro.

---

### TAREFA 2: Expandir constraint do Laravel no composer.json (P0 - Obrigatório)

**Arquivo**: `composer.json`

**Antes**:
```json
"laravel/framework": "^5.0||^6.0"
```

**Depois**:
```json
"laravel/framework": "^6.0||^7.0||^8.0||^9.0||^10.0||^11.0||^12.0||^13.0"
```

Também atualizar `php`:
```json
"php": "^7.4||^8.0||^8.1||^8.2||^8.3||^8.4||^8.5"
```

E `require-dev`:
```json
"require-dev": {
    "phpunit/phpunit": "^9.0||^10.0||^11.0",
    "orchestra/testbench": "^6.0||^7.0||^8.0||^9.0||^10.0"
}
```

**Justificativa**: O uso de Illuminate se limita a classes estáveis (`Arr::get`, `Collection`, `Str::random`, `UploadedFile`). Todas existem desde Laravel 5 até 13 sem breaking changes na API usada.

---

### TAREFA 3: Corrigir `src/Blade.php` para Laravel 13 (P1 - Necessário)

**Arquivo**: `src/Blade.php`, linha 140

**Problema**: `new Dispatcher()` sem argumentos causa `TypeError` no Laravel 9+.
O construtor de `Illuminate\Events\Dispatcher` passou a exigir `ContainerInterface` ou `null`.

**Antes** (linha 140):
```php
$env = new Factory($resolver, $finder, new Dispatcher);
```

**Depois**:
```php
$env = new Factory($resolver, $finder, new Dispatcher($this->container));
```

**Contexto**: Este arquivo só é executado como fallback quando NÃO há app Laravel ativa (verificado em `Html.php:38-46`). No `r2-boletos-render` (que é Laravel completo), o caminho normal via `Container::getInstance()` é usado e este arquivo nem roda. Mas a correção é necessária para o caminho standalone.

---

### TAREFA 4: Testar instalação no projeto Laravel 13 (P1 - Necessário)

Após as correções acima, testar no projeto `r2-boletos-render/new/`:

```bash
# No container Docker (PHP 8.3)
cd /app
composer require r2soft/laravel-boleto:dev-feature/laravel-13-compat --ignore-platform-reqs
```

Verificar que:
1. `composer install` sem erros
2. As classes do pacote são descobertas pelo autoloader
3. Executar testes existentes:
```bash
./vendor/bin/phpunit
```

---

### TAREFA 5: Commit e tag (P2 - Após validação)

```bash
git add -A
git commit -m "compat: expand Laravel support to 6-13, fix PHP 8.3 deprecation"
git tag 00.010.017
git push origin feature/laravel-13-compat
```

---

## Mapa de dependências Illuminate

| Classe | Arquivos que usam | Método usado | Risco Laravel 13 |
|--------|-------------------|--------------|------------------|
| `Illuminate\Support\Arr` | 19 arquivos em `Cnab/Retorno/` | `Arr::get($arr, $key, $default)` | Baixo - estável desde L4 |
| `Illuminate\Support\Collection` | 5 arquivos em `Cnab/Retorno/` | `new Collection()`, `->push()` | Baixo - estável desde L4 |
| `Illuminate\Support\Str` | `Pdf.php`, `PdfCaixa.php` | `Str::random(32)` | Baixo - estável desde L4 |
| `Illuminate\Http\UploadedFile` | `Util.php` | `instanceof` + `getRealPath()` | Baixo |
| `Illuminate\Container\Container` | `Blade.php`, `Html.php` | `getInstance()`, `resolved()` | Médio |
| `Illuminate\View\*` (9 classes) | `Blade.php` | Stack completa de Views | **Alto** - construtores mudaram |
| `Illuminate\Events\Dispatcher` | `Blade.php:140` | `new Dispatcher()` | **Alto** - requer Container arg |

---

## Estrutura de diretórios do pacote

```
src/
├── Blade.php                    ← Stack de Views standalone (fallback)
├── CalculoDV.php                ← Cálculo de DV por banco
├── MagicTrait.php               ← Trait mágica getters/setters
├── Pessoa.php                   ← DTO pagador/beneficiário
├── Util.php                     ← Helpers diversos
├── Boleto/
│   ├── AbstractBoleto.php       ← Classe base de boleto
│   ├── Banco/
│   │   ├── Bancoob.php          ← Sicoob
│   │   ├── Banrisul.php
│   │   ├── Bb.php               ← Banco do Brasil
│   │   ├── Bnb.php
│   │   ├── Bradesco.php
│   │   ├── Brb.php
│   │   ├── Caixa.php
│   │   ├── Hsbc.php
│   │   ├── Itau.php
│   │   ├── Qiscd.php
│   │   ├── Santander.php
│   │   ├── Sicredi.php
│   │   └── Unicred.php
│   └── Render/
│       ├── AbstractPdf.php      ← ← P0: utf8_decode deprecated
│       ├── Html.php             ← Usa Blade (caminho Laravel ou fallback)
│       ├── Pdf.php              ← Usa Str::random() + FPDF
│       ├── PdfCaixa.php         ← Variação Caixa
│       ├── cache/               ← Cache de views compiladas
│       └── view/                ← Templates Blade do boleto
├── Cnab/
│   ├── Remessa/
│   │   ├── AbstractRemessa.php
│   │   ├── Cnab240/             ← Remessa formato 240
│   │   └── Cnab400/             ← Remessa formato 400
│   └── Retorno/
│       ├── AbstractRetorno.php
│       ├── Factory.php
│       ├── Cnab240/             ← Retorno formato 240 (usa Arr::get)
│       └── Cnab400/             ← Retorno formato 400 (usa Arr::get)
└── Contracts/
    ├── Boleto/
    │   ├── Boleto.php
    │   └── Render/
    │       ├── Pdf.php
    │       └── Html.php
    ├── Cnab/
    │   ├── Remessa.php
    │   ├── RemessaCnab240.php
    │   ├── RetornoCnab240.php   ← type-hint Collection
    │   └── RetornoCnab400.php   ← type-hint Collection
    └── Pessoa.php
```

---

## Bancos suportados

| Código | Banco | Classe | Usa no render? |
|--------|-------|--------|----------------|
| 001 | Banco do Brasil | `Bb` | Sim |
| 033 | Santander | `Santander` | Sim |
| 104 | Caixa Econômica | `Caixa` | Sim |
| 237 | Bradesco | `Bradesco` | Sim |
| 341 | Itaú | `Itau` | Sim |
| 748 | Sicredi | `Sicredi` | Sim |
| 756 | Bancoob/Sicoob | `Bancoob` | Sim |
| 041 | Banrisul | `Banrisul` | Não |
| 004 | BRB | `Brb` | Não |
| 003 | BNB | `Bnb` | Não |
| 399 | HSBC | `Hsbc` | Não |
| 136 | Unicred | `Unicred` | Não |
| 077 | QISCD/Inter | `Qiscd` | Não |

---

## Como o r2-boletos-render usa este pacote

O render usa extensivamente em **20+ arquivos**:

| Arquivo no render | O que importa do laravel-boleto |
|---|---|
| `app/Factory/BoletoFactory.php` | Classes de bancos (Bs2, Qiscd, Brb, etc.) |
| `app/Model/Sicredi.php` | `Boleto\Banco\Sicredi as SicrediOriginal` |
| `app/Model/Itau.php` | `Boleto\Banco\Itau as ItauOriginal` + `Util` |
| `app/Model/Bb.php` | `Boleto\Banco\Bb as BbOriginal` |
| `app/Model/Santander.php` | `Boleto\Banco\Santander as SantanderOriginal` |
| `app/Model/Bradesco.php` | `Boleto\Banco\Bradesco as BradescoOriginal` |
| `app/Model/Sicoob.php` | `Boleto\Banco\Bancoob as SicoobOriginal` |
| `app/Model/Asaas.php` | `Boleto\Banco\Asaas as AsaasOriginal` |
| `app/Model/Canvi.php` | `Boleto\Banco\Canvi as CanviOriginal` |
| `app/Model/Efi.php` | `Boleto\Banco\Efi as EfiOriginal` |
| `app/Model/Sisprime.php` | `Boleto\Banco\Sisprime as SisprimeOriginal` |
| `app/Model/Unicred.php` | `Boleto\Banco\Unicred as UnicredOriginal` |
| `app/Model/CalculoDV.php` | `CalculoDV as CalculoDVOriginal` + `Util` |
| `app/Model/CaixaR2Soft.php` | `Boleto\Banco\Caixa` + `Util` |
| `app/Model/BluPay.php` | `Boleto\Banco\Bb as BbOriginal` |
| `app/Bo/BilletBo.php` | `Pessoa` |
| `app/Service/Pdf.php` | `Render\AbstractPdf`, `Contracts\Boleto\Boleto`, `Contracts\Boleto\Render\Pdf`, `Util` |
| `app/Service/Carne.php` | `Render\AbstractPdf`, `Contracts\Boleto\Boleto`, `Contracts\Boleto\Render\Pdf`, `Util` |
| `app/Service/PdfBluPay.php` | `Render\AbstractPdf`, `Contracts\Boleto\Boleto`, `Util` |
| `app/Service/CarneBluPay.php` | `Render\AbstractPdf`, `Contracts\Boleto\Boleto`, `Util` |
| `app/Helpers/Bank.php` | `Util` |

---

## Notas adicionais

- Os Models no render (Sicredi, Itau, etc.) **estendem** as classes do laravel-boleto e adicionam customizações da R2Soft
- As classes `Asaas`, `Canvi`, `Efi`, `Sisprime`, `BluPay` **não existem** no laravel-boleto original - foram criadas no render como extensões
- O `r2-boletos-render` também usa pacotes adicionais que dependem de banco: `r2soft/r2-sicredi-api` para chamadas diretas à API do Sicredi
