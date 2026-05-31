# Magick AI Cloud Addon Positioning

## English

Magick AI Cloud Addon is the thin Cloud connector for Magick AI hosted runtime
access. It connects a local WordPress site to `magick-ai-cloud` through Cloud
Base URL and Cloud API Key settings, signed runtime requests, health checks,
and entitlement summaries.

Magick AI Cloud Addon is part of the Magick AI plugin family:

- `magick-ai-abilities` - ability definitions and ability callbacks.
- `magick-ai-core` - governance, approval, preflight, and audit.
- `magick-ai-adapter` - OpenClaw channel adaptation that calls Core and the
  Abilities API.
- `magick-ai-cloud-addon` - cloud service connection, signing, health checks,
  and entitlement summaries.

The addon keeps local WordPress as the control plane. Cloud remains a hosted
runtime and service enhancement layer. The addon does not execute WordPress
writes, approve proposals, own billing truth, manage prompts, manage routers,
own presets, run queues, schedule jobs, or become a workflow engine.

## Chinese

Magick AI Cloud Addon 是 Magick AI hosted runtime access 的薄 Cloud 连接器。
它通过 Cloud Base URL 和 Cloud API Key 设置、签名 runtime requests、health
checks 和 entitlement summaries，把本地 WordPress 站点连接到 `magick-ai-cloud`。

Magick AI Cloud Addon 是 Magick AI 系列插件的一部分：

- `magick-ai-abilities` - 能力定义和 ability callback。
- `magick-ai-core` - 治理、审批、preflight、audit。
- `magick-ai-adapter` - OpenClaw 通道适配，调用 Core 和 Abilities API。
- `magick-ai-cloud-addon` - 云端服务连接、签名、健康检查和 entitlement 摘要。

Addon 保持本地 WordPress 作为控制面。Cloud 仍然是 hosted runtime 和 service
enhancement layer。Addon 不执行 WordPress 写入、不审批 proposals、不拥有 billing
truth、不管理 prompts、不管理 routers、不拥有 presets、不运行 queues、不调度 jobs，
也不成为 workflow engine。
