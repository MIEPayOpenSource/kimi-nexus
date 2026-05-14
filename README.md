# Kimi Nexus

`Kimi Nexus` 是一个用于公司内部多个项目快速对接第三方 API 的 Composer 库。

## 目标

- 统一 API 对接方式（认证、重试、超时、错误处理）
- 降低新项目接入 API 的重复工作
- 提供可扩展的集成目录结构

## 安装

```bash
composer install
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

$config = new ApiConfig(
    'https://api.business.govt.nz/',
    'your-api-key',
    10.0,
    [
        'Accept' => 'application/json',
    ]
);

$client = BusinessGovtNzGateway::make($config);

// 默认请求 GET /helloworld
$result = $client->helloWorld();

// 如果文档里的联通测试路径不是 /helloworld，可覆盖路径
// $result = $client->helloWorld('/v1/health');

var_dump($result);
```

## 已提供的 Business.govt.nz 入口

- `BusinessGovtNzGateway::make($config)`: 快速创建客户端入口
- `BusinessGovtNzApiClient::helloWorld($path = '/helloworld')`: HelloWorld 联通接口

## 后续建议

- 为每个供应商建立独立目录：`src/Integrations/{Vendor}`
- 每个集成包含：`Client`、`DTO`、`Exception`
- 新增统一重试策略、日志追踪（trace id）、熔断与限流
