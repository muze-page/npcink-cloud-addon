# WordPress.org 上架文案草稿 - 中文

## 插件名称

Npcink Cloud Addon

## 简短描述

面向 Npcink hosted runtime access 的薄 Cloud 连接器，负责签名、健康检查和 entitlement 摘要。

## 标签建议

magick ai, cloud, hosted runtime, ai, connector

## 插件介绍

Npcink Cloud Addon 将本地 WordPress 站点连接到 `npcink-cloud`。

它存储 Cloud Base URL 和 Cloud API Key，解析 Cloud-issued keys，在服务端签名
runtime requests，探测 Cloud health，并读取 Cloud entitlement summaries 用于本地展示。

Addon 有意保持很薄。它是 cloud service connection layer，不是本地控制面、
governance layer、ability registry、workflow engine、router owner、prompt owner、
preset owner、queue owner、scheduler、billing truth source，也不是 WordPress 写入执行器。

本地 WordPress 仍然是控制面。最终 WordPress 写入仍然必须经过本地 Core proposal、
approval、preflight 和 apply paths。Cloud 仍然是 hosted runtime 和 service
enhancement layer。

## 核心功能

- 保存 Cloud Base URL 和 Cloud API Key 设置。
- 将面向客户的 Cloud API Key 解析成签名凭据。
- 在服务端签名 hosted runtime requests。
- 探测 Cloud liveness 和 signed verification 状态。
- 读取 Cloud entitlement summaries 用于本地展示。
- 为本地插件暴露一个小型 PHP interface。
- 将 Cloud connection 与 governance、abilities、adapter routing、model prompts、
  presets、queues、schedulers 和最终 WordPress 写入保持分层。

## 适合谁使用

- 需要把本地 Npcink setup 连接到 Npcink Cloud 的 WordPress 管理员。
- 希望接入 hosted runtime access，同时保留本地 governance truth 的 Npcink 部署。
- 需要窄服务端 Cloud transport seam 的开发者。

## 环境要求

- WordPress 7.0 或更高版本。
- PHP 8.0 或更高版本。
- 由 Npcink Cloud 签发的 Cloud Base URL 和 Cloud API Key。

## 外部服务

这个插件会连接到站点管理员在 Cloud Base URL 设置中配置的 Npcink Cloud
服务。

插件只会在管理员填写 Cloud Base URL 和 Cloud API Key、保存设置、验证连接，
或本地 Npcink 组件明确调用 Cloud runtime client 时，联系已配置的 Cloud
服务。

请求可能包含已配置的 site identifier、key identifier、请求时间戳、nonce、
trace identifier、idempotency key、HMAC signature headers、本地 Npcink
组件提供的 runtime request payload，以及用于 health、run status、run result、
usage statistics 和 entitlement summaries 的只读请求。已保存的 Cloud API Key
secret 只在服务端用于请求签名，不会打印到 wp-admin 页面。

已配置的 Cloud 服务负责自己的隐私政策、服务条款、数据保留规则和账号/key 签发。
站点管理员应只连接到已经审阅过隐私政策和服务条款的 Cloud 服务。

## 系列插件边界

在 Npcink 系列插件中：

- Npcink Abilities Toolkit 负责能力定义和 ability callback。
- Npcink Governance Core 负责治理、审批、preflight、audit。
- Npcink OpenClaw Adapter 负责 OpenClaw 通道适配。
- Npcink Cloud Addon 负责 Cloud service connection 和 signing。

这个分层让 Cloud 保持 hosted runtime 和 service enhancement layer，而不会把本地
治理、审批、prompts、router truth 或最终 WordPress 写入迁移到 addon。
