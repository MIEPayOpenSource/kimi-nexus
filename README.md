# Kimi Nexus

`Kimi Nexus` 是一个用于公司内部多个项目快速对接第三方 API 的 Composer 库。

## 目标

- 统一 API 对接方式（认证、重试、超时、错误处理）
- 降低新项目接入 API 的重复工作
- 提供可扩展的集成目录结构

## 安装（作为库开发）

```bash
composer install
```

## 安装（在其他项目中使用）

```bash
composer require caobowen/kimi-nexus:^0.1
```

说明：首次发布前可先使用 `dev-main`。

```bash
composer require caobowen/kimi-nexus:dev-main
```

## 目录结构

- `src/Core`: 通用能力（配置、HTTP 客户端、异常）
- `src/Integrations`: 各 API 的具体集成实现
- `tests`: 测试目录

## 快速开始（Business.govt.nz）

```php
<?php

use KimiNexus\Core\ApiConfig;
use KimiNexus\Integrations\BusinessGovtNz\BusinessGovtNzGateway;

$config = new ApiConfig('https://api.business.govt.nz/');

$client = BusinessGovtNzGateway::make($config);

// 示例接口：不发真实 HTTP，请求结果固定返回 helloworld
$result = $client->helloWorld();

var_dump($result);
```

## 已提供的 Business.govt.nz 入口

- `BusinessGovtNzGateway::make($config)`: 快速创建客户端入口
- `BusinessGovtNzApiClient::helloWorld($path = '/helloworld')`: HelloWorld 本地示例接口（固定返回）

## ABN Lookup（Australia ABR）

可选环境变量示例：

```dotenv
ABN_LOOKUP_BASE_URI=https://abr.business.gov.au/json/
ABN_LOOKUP_GUID=your-abr-guid
ABN_LOOKUP_TIMEOUT=20
```

```php
<?php

use KimiNexus\Core\ApiConfig;
use KimiNexus\Integrations\AbnLookup\AbnLookupGateway;

$baseUri = getenv('ABN_LOOKUP_BASE_URI') ?: 'https://abr.business.gov.au/json/';
$timeout = (float) (getenv('ABN_LOOKUP_TIMEOUT') ?: 20);
$guid = (string) getenv('ABN_LOOKUP_GUID');

$config = new ApiConfig($baseUri, null, $timeout);

$client = AbnLookupGateway::make($config, $guid);

// 1) 按 ABN 查询
$byAbn = $client->searchByAbn('51 824 753 556');

// 2) 按名称模糊查询
$byName = $client->searchByName('COMMONWEALTH BANK', 20, false);
```

已提供入口：

- `AbnLookupGateway::make($config, $guid)`: 创建 ABN Lookup 客户端
- `AbnLookupApiClient::searchByAbn($abn)`: 按 ABN 查询
- `AbnLookupApiClient::searchByName($name, $maxResults = 20, $withDetails = false)`: 按企业名查询

## 后续建议

- 为每个供应商建立独立目录：`src/Integrations/{Vendor}`
- 每个集成包含：`Client`、`DTO`、`Exception`
- 新增统一重试策略、日志追踪（trace id）、熔断与限流
