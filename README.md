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

$baseUri = getenv('BUSINESS_GOVTNZ_BASE_URI') ?: 'https://api.business.govt.nz/';
$timeout = (float) (getenv('BUSINESS_GOVTNZ_TIMEOUT') ?: 20);
$subscriptionKey = getenv('BUSINESS_GOVTNZ_PRIMARY_KEY') ?: getenv('BUSINESS_GOVTNZ_SECONDARY_KEY');

$config = new ApiConfig($baseUri, null, $timeout, [
    'Ocp-Apim-Subscription-Key' => (string) $subscriptionKey,
]);

$client = BusinessGovtNzGateway::make($config);

// 示例接口：不发真实 HTTP，请求结果固定返回 helloworld
$result = $client->helloWorld();

var_dump($result);

// NZBN 按名称搜索实体
$entities = $client->searchEntitiesByName('acme', [
    'entity_status' => ['Registered', 'VoluntaryAdministration'],
    'entity_type' => ['NZCompany', 'OverseasCompany'],
    'industry_code' => 'M692250',
    'page' => 0,
    'page_size' => 20,
]);

var_dump($entities);

// NZBN 查单个实体详情
$entity = $client->viewEntityByNzbn('9429040000000', [
    'request_id' => '2a444f3f-a486-4f7c-9bcf-e02095fd3576', // 可选，对应 api-business-govt-nz-Request-Id
    'if_none_match' => 'W/"previous-etag"', // 可选，对应 If-None-Match
]);

var_dump($entity);
```

## Business.govt.nz 环境变量

建议在 `.env` 中配置以下变量。

必填变量：

```dotenv
BUSINESS_GOVTNZ_PRIMARY_KEY=your-primary-key
```

选填变量：

```dotenv
BUSINESS_GOVTNZ_BASE_URI=https://api.business.govt.nz/
BUSINESS_GOVTNZ_TIMEOUT=20
BUSINESS_GOVTNZ_SECONDARY_KEY=your-secondary-key
```

说明：
- `BUSINESS_GOVTNZ_PRIMARY_KEY`：首选订阅 key（必填）。
- `BUSINESS_GOVTNZ_SECONDARY_KEY`：主 key 不可用时的回退 key（可选）。
- `BUSINESS_GOVTNZ_BASE_URI`：
  - 生产环境：`https://api.business.govt.nz/`
  - Sandbox：`https://api.business.govt.nz/sandbox`
- `BUSINESS_GOVTNZ_TIMEOUT`：请求超时秒数（默认 `20`）。
- NZBN API 认证关键头是 `Ocp-Apim-Subscription-Key`。
- 若你把 key 放在 `ApiConfig` 第 2 个参数（`apiKey`）里，`BusinessGovtNzGateway` 会自动兼容映射到 `Ocp-Apim-Subscription-Key`。

## Business.govt.nz 调用指引

1. 在环境变量中设置 key（至少 `BUSINESS_GOVTNZ_PRIMARY_KEY`）。
2. 根据环境设置 `BUSINESS_GOVTNZ_BASE_URI`：
   - 生产：`https://api.business.govt.nz/`
   - Sandbox：`https://api.business.govt.nz/sandbox`
3. 用 `BusinessGovtNzGateway::make($config)` 创建客户端。
4. 先调用 `searchEntitiesByName()` 检索 NZBN，再调用 `viewEntityByNzbn()` 拉实体详情。
5. 若返回 `401 missing subscription key`，优先检查：
   - key 是否为空
   - `BASE_URI` 与 key 所属环境是否匹配（生产 key 对生产地址，sandbox key 对 sandbox 地址）

最小调用示例（搜索 + 详情）：

```php
<?php

use KimiNexus\Core\ApiConfig;
use KimiNexus\Integrations\BusinessGovtNz\BusinessGovtNzGateway;

$baseUri = getenv('BUSINESS_GOVTNZ_BASE_URI') ?: 'https://api.business.govt.nz/';
$timeout = (float) (getenv('BUSINESS_GOVTNZ_TIMEOUT') ?: 20);
$subscriptionKey = getenv('BUSINESS_GOVTNZ_PRIMARY_KEY') ?: getenv('BUSINESS_GOVTNZ_SECONDARY_KEY');

$config = new ApiConfig($baseUri, null, $timeout, [
    'Ocp-Apim-Subscription-Key' => (string) $subscriptionKey,
]);

$client = BusinessGovtNzGateway::make($config);

$entities = $client->searchEntitiesByName('acme', [
    'entity_status' => ['Registered'],
    'page' => 0,
    'page_size' => 5,
]);

$firstNzbn = $entities['data']['items'][0]['nzbn'] ?? null;
if ($firstNzbn !== null) {
    $detail = $client->viewEntityByNzbn($firstNzbn);
    var_dump($detail);
}
```

## 已提供的 Business.govt.nz 入口

- `BusinessGovtNzGateway::make($config)`: 快速创建客户端入口
- `BusinessGovtNzApiClient::helloWorld($path = '/helloworld')`: HelloWorld 本地示例接口（固定返回）
- `BusinessGovtNzApiClient::searchEntitiesByName($searchTerm, $filters = [])`: 调用 `GET /{gateway|sandbox}/nzbn/v5/entities` 进行实体名称搜索
- `BusinessGovtNzApiClient::viewEntityByNzbn($nzbn, $options = [])`: 调用 `GET /{gateway|sandbox}/nzbn/v5/entities/{nzbn}` 获取实体详情

## ABN Lookup（Australia ABR）

可选环境变量示例：

```dotenv
ABN_LOOKUP_BASE_URI=https://abr.business.gov.au/json/
ABN_LOOKUP_GUID=your-abr-guid
ABN_LOOKUP_TIMEOUT=20
```

说明：`ABN_LOOKUP_BASE_URI` 建议使用 `https://abr.business.gov.au/json/`（末尾带 `/`）。

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

## Veriff KYC：人脸比对与活体检测

`VeriffGateway` 封装了 Demo 的「创建会话 → 打开 Veriff 拍照页 → 查询结果」流程。你当前后台截图所示的证件与自拍核验，可直接使用 `createSession()` 创建会话；用户按 Veriff 页面提示拍摄证件和自拍，服务端再用 `getDecision()` 查询结果。Veriff 的 [Document + Selfie IDV](https://devdocs.veriff.com/docs/document-selfie-idv) 用于综合身份核验；其[身份核验产品说明](https://www.veriff.com/pricing)列出了证件检查、人脸比对和被动活体检测。

在 KYC 中可以把 Veriff 的最终 `approved` 决定作为该次身份核验通过的信号。`getDecision()` 返回的是整次核验的决定，不保证包含可独立读取的“活体分数”或“人脸相似度”。截图中的示例会话及 API 连通性测试也不能证明实际用户的活体检测已端到端完成；上线前应使用当前集成完成一次真实拍摄，确认 Veriff 页面要求自拍、决定结果正常，并核对该集成启用的检查项。

这里的 `vendorData` / `endUserId` 是可选的业务用户标识，便于将 Veriff 会话关联到你系统中的用户；它们**不是**查询本次证件与自拍核验结果的前提。后台显示 `Vendor Data: -` 的会话仍可有 `Approved` 结果。

### 配置

将以下值放到调用方项目的服务端环境变量中，使用证件与自拍核验集成的实际凭据替换占位符：

```dotenv
VERIFF_BASE_URL=https://api-saas.veriff.com
VERIFF_API_KEY=your-veriff-integration-api-key
VERIFF_SHARED_SECRET=your-veriff-integration-shared-secret
VERIFF_TIMEOUT=15
```

`VERIFF_BASE_URL` 应以 Veriff 为该集成分配的 API 地址为准。不要将 API Key 或 Shared Secret 放进网页代码、移动端代码或提交到仓库。

### 创建核验会话

```php
<?php

use KimiNexus\Core\ApiConfig;
use KimiNexus\Integrations\Veriff\VeriffGateway;

$client = VeriffGateway::make(
    new ApiConfig(
        getenv('VERIFF_BASE_URL') ?: 'https://api-saas.veriff.com',
        null,
        (float) (getenv('VERIFF_TIMEOUT') ?: 15)
    ),
    (string) getenv('VERIFF_API_KEY'),
    (string) getenv('VERIFF_SHARED_SECRET')
);

$session = $client->createSession();
$sessionId = $session['id'];
$verificationUrl = $session['url'];

// 将 $sessionId 与当前登录用户关联并持久化，再将 $verificationUrl 交给该用户的浏览器打开。
// 如果想在 Veriff 中附带业务用户标识，可改为：$client->createSession(['vendorData' => $opaqueUserId]);
```

### 查询结果

用户在 Veriff 页面完成证件与自拍拍摄后，服务端用保存的会话 ID 查询。结果可能需要稍后才可用；查询接口会验证 Veriff 响应的 HMAC 签名。

```php
<?php

// $client 按上面的方式创建；$savedSessionId 来自当前用户的服务端记录。
$decision = $client->getDecision($savedSessionId);
if ($decision === null) {
    // 结果尚未就绪，稍后重试。
} elseif (
    ($decision['code'] ?? null) === 9001
    && ($decision['status'] ?? null) === 'approved'
) {
    // 本次证件与自拍身份核验通过。
} else {
    // 根据 status、code 和 reasonCode 处理失败、重试或过期。
}
```

`getDecision(string $sessionId)` 返回完整的 `verification` 对象，尚未出结果时返回 `null`。创建请求与查询请求均带 HMAC 签名，客户端也会校验成功响应的签名。生产环境应只允许当前用户查询自己的会话，不要让浏览器传入任意 session ID 作为核验依据。

### 可选：已录入用户再次刷脸

如果目标是让同一用户以后**不再出示证件，仅自拍登录或复核身份**，那是 Veriff 的 [Biometric Authentication](https://devdocs.veriff.com/docs/biometric-authentication) 产品。它需要已关联的人脸录入与认证集成，以及录入时使用的稳定 `endUserId` 或 `vendorData`。这时才使用 `createBiometricAuthenticationSession(?string $endUserId = null, ?string $vendorData = null)`，并使用认证集成的凭据。已通过的普通核验会话若没有录入标识，不能直接把 Session ID 充当该标识；旧照片迁移需与 Veriff 确认。

## 后续建议

- 为每个供应商建立独立目录：`src/Integrations/{Vendor}`
- 每个集成包含：`Client`、`DTO`、`Exception`
- 新增统一重试策略、日志追踪（trace id）、熔断与限流
