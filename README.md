---
license: mit
base_model:
- JibayAi/Jibay_NSFW_1
pipeline_tag: image-to-text
tags:
- image to text
- image to image
- JibayAI
- NSFW
- mit
- jibay.ir
- Filter
- image
- php
- imagick
- GD
- Version q
library_name: imagick & GD
language:
- en
- fa
- ar
- es
- fr
- zh
- ko
- ja
---


# Jibay NSFW-1
### Lightweight, Fast, Open-Source NSFW Image Detection & Filtering Engine

<div align="center">

![License](https://img.shields.io/badge/license-MIT-green.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)
![Open Source](https://img.shields.io/badge/Open%20Source-Yes-success)
![GPU](https://img.shields.io/badge/GPU-Not%20Required-orange)
![CPU](https://img.shields.io/badge/CPU-Optimized-blueviolet)

**Developed by the Jibay Team**

🌐 **Website:** https://jibay.ir

</div>

---

# English

## Overview

**Jibay NSFW-1** is an extremely lightweight, high-speed, open-source image moderation engine written entirely in PHP.

Its purpose is to detect inappropriate (NSFW / Explicit / Adult) images and automatically identify them for moderation systems.

Unlike AI models that require GPUs and gigabytes of memory, this project runs completely on CPU and works on almost every PHP hosting environment.

It is designed to work from:

- Shared Hosting
- VPS
- Dedicated Servers
- Cloud Servers
- Localhost
- Home Servers

No GPU is required.

No Python is required.

No Machine Learning libraries are required.

Simply upload the files and start using it.

---

# Features

- Extremely lightweight
- Very fast processing
- Open Source
- MIT Licensed
- CPU Only
- No GPU Needed
- No AI Frameworks
- No Python Required
- Works with PHP
- Automatic NSFW Detection
- Automatic Explicit Image Recognition
- Automatic Image Moderation
- Supports JPEG
- Supports PNG
- Supports GIF
- Supports WEBP
- Supports AVIF
- Supports HEIC
- Works with Imagick
- Works with GD Library
- Automatic image download
- Built-in security checks
- Built-in URL validation
- Public network validation
- Configurable thresholds
- Blur explicit images automatically
- Save processed images
- JSON API
- Very low memory usage
- Open source

---

# How It Works

Unlike deep learning models, Jibay NSFW-1 uses an advanced image analysis engine based on computer vision and statistical image processing.

The engine analyzes many characteristics of an image including:

- Skin probability
- Chromatic skin detection
- Connected regions
- Body coverage estimation
- Anatomical pattern detection
- Spatial topology
- Texture analysis
- Color entropy
- White garment protection
- False positive reduction
- Confidence estimation
- Region analysis
- Pixel clustering

All of these signals are combined into a confidence score that determines whether an image is likely to contain explicit content.

---

# Installation

Simply upload all project files to your server.

Requirements:

- PHP 8.1+
- GD or Imagick extension
- Write permission for storage folder

No additional installation is required.

---

# Quick Start

Example endpoint:

```
https://your-domain.com/index.php?url=https://example.com/image.jpg
```

---

# Example Request

```
GET /index.php?url=https://example.com/photo.jpg
```

---

# Example Success Response

```json
{
    "success": true,
    "explicit": false,
    "score": 0.12,
    "confidence": "Very High",
    "processed_image": "storage/moderated/moderated_8c2ab4.jpg"
}
```

---

# Example Explicit Detection

```json
{
    "success": true,
    "explicit": true,
    "score": 0.94,
    "confidence": "Very High",
    "processed_image": "storage/moderated/moderated_d912ff.jpg"
}
```

---

# Example Errors

Invalid URL

```json
{
    "success": false,
    "code": "INVALID_URL",
    "message": "A valid image URL is required."
}
```

---

Image Too Large

```json
{
    "success": false,
    "code": "IMAGE_TOO_LARGE",
    "message": "The remote image exceeds the configured size limit."
}
```

---

Unsupported Format

```json
{
    "success": false,
    "code": "UNSUPPORTED_IMAGE_FORMAT",
    "message": "The image format is not supported."
}
```

---

Download Failed

```json
{
    "success": false,
    "code": "DOWNLOAD_FAILED",
    "message": "The remote image could not be downloaded."
}
```

---

# Project Structure

```
project/

├── index.php
├── config.json
├── storage/
│   └── moderated/
├── tokens.json
└── README.md
```

---

# Performance

Runs on:

- Shared Hosting
- Cheap VPS
- Raspberry Pi
- Mini PC
- Intel CPU
- AMD CPU
- Virtual Machines

No graphics card is needed.

Memory consumption is extremely low.

Startup time is almost instant.

---

# Important Notes

This is Version 1 of the engine.

Although it performs very well, no image moderation system is 100% perfect.

Some images may occasionally be classified incorrectly.

We strongly recommend manual review for sensitive applications.

Future versions will improve accuracy even further.

---

# License

This project is released under the MIT License.

Everyone is free to:

- Use
- Modify
- Fork
- Improve
- Redistribute
- Publish
- Use commercially

**However, keeping the name "Jibay" somewhere in the project or credits is mandatory.**

---

# Credits

Developed with ❤️ by

# Jibay Team

Website:

https://jibay.ir

---

---

# فارسی

# Jibay NSFW-1

### موتور متن‌باز، سریع و بسیار سبک تشخیص تصاویر نامناسب

---

## معرفی

**Jibay NSFW-1** یک موتور بسیار سبک، سریع و متن‌باز برای تشخیص تصاویر نامناسب (NSFW) است که به طور کامل با زبان PHP توسعه داده شده است.

هدف این پروژه شناسایی تصاویر مستهجن، بزرگسال، نامناسب و محتوای حساس جهت استفاده در سیستم‌های مدیریت محتوا، شبکه‌های اجتماعی، انجمن‌ها، سایت‌ها و APIها می‌باشد.

برخلاف مدل‌های هوش مصنوعی که به کارت گرافیک و چندین گیگابایت حافظه نیاز دارند، این پروژه تنها با پردازنده (CPU) اجرا می‌شود.

این پروژه روی تقریباً هر نوع هاستی اجرا می‌شود.

از ضعیف‌ترین هاست اشتراکی گرفته تا قوی‌ترین سرورها.

بدون نیاز به کارت گرافیک.

بدون نیاز به Python.

بدون نیاز به TensorFlow.

بدون نیاز به PyTorch.

فقط فایل‌ها را آپلود کنید و اجرا نمایید.

---

# ویژگی‌ها

- متن‌باز
- رایگان
- بسیار سبک
- بسیار سریع
- مصرف بسیار کم حافظه
- بدون GPU
- بدون Python
- بدون کتابخانه‌های AI
- فقط PHP
- تشخیص خودکار تصاویر مستهجن
- فیلتر تصاویر نامناسب
- ذخیره خروجی
- محو کردن تصاویر نامناسب
- API مبتنی بر JSON
- امنیت داخلی
- بررسی آدرس تصویر
- پشتیبانی از فرمت‌های رایج تصاویر
- قابل اجرا روی هاست اشتراکی
- قابل اجرا روی VPS
- قابل اجرا روی سرور اختصاصی

---

# نحوه عملکرد

این پروژه از مدل‌های هوش مصنوعی استفاده نمی‌کند.

در عوض با استفاده از مجموعه‌ای از الگوریتم‌های پردازش تصویر، بینایی ماشین و تحلیل آماری، ویژگی‌های مختلف تصویر را بررسی می‌کند.

از جمله:

- احتمال پوست انسان
- تحلیل رنگ پوست
- تحلیل نواحی متصل
- تشخیص الگوهای آناتومی
- بررسی گستردگی بدن
- تحلیل موقعیت اجزاء
- تحلیل بافت تصویر
- بررسی تراکم رنگ
- جلوگیری از خطاهای ناشی از لباس سفید
- کاهش خطاهای مثبت
- محاسبه میزان اطمینان

در پایان تمامی این سیگنال‌ها با هم ترکیب شده و نتیجه نهایی تولید می‌شود.

---

# نصب

کافی است فایل‌های پروژه را روی هاست یا سرور خود آپلود نمایید.

پیش‌نیازها:

- PHP 8.1 یا بالاتر
- افزونه GD یا Imagick
- دسترسی نوشتن برای پوشه Storage

هیچ نصب دیگری نیاز نیست.

---

# نمونه اجرا

```
https://your-domain.com/index.php?url=https://example.com/image.jpg
```

---

# نمونه پاسخ موفق

```json
{
    "success": true,
    "explicit": false,
    "score": 0.18,
    "confidence": "Very High",
    "processed_image": "storage/moderated/moderated_xxx.jpg"
}
```

---

# نمونه تشخیص محتوای نامناسب

```json
{
    "success": true,
    "explicit": true,
    "score": 0.96,
    "confidence": "Very High",
    "processed_image": "storage/moderated/moderated_xxx.jpg"
}
```

---

# نمونه خطاها

آدرس نامعتبر

```json
{
    "success": false,
    "code": "INVALID_URL",
    "message": "A valid image URL is required."
}
```

---

تصویر بیش از حد بزرگ

```json
{
    "success": false,
    "code": "IMAGE_TOO_LARGE",
    "message": "The remote image exceeds the configured size limit."
}
```

---

فرمت پشتیبانی نمی‌شود

```json
{
    "success": false,
    "code": "UNSUPPORTED_IMAGE_FORMAT",
    "message": "The image format is not supported."
}
```

---

دانلود تصویر ناموفق

```json
{
    "success": false,
    "code": "DOWNLOAD_FAILED",
    "message": "The remote image could not be downloaded."
}
```

---

# ساختار پروژه

```
project/

├── index.php
├── config.json
├── storage/
│   └── moderated/
├── tokens.json
└── README.md
```

---

# عملکرد

این پروژه روی موارد زیر اجرا می‌شود:

- هاست اشتراکی
- VPS
- سرور اختصاصی
- کلود
- Raspberry Pi
- Mini PC
- CPUهای Intel
- CPUهای AMD

نیازی به کارت گرافیک ندارد.

مصرف حافظه بسیار پایین است.

سرعت پردازش بسیار بالا است.

---

# نکات مهم

این پروژه نسخه اول موتور Jibay NSFW-1 است.

اگرچه دقت بسیار بالایی دارد، اما هیچ سیستم تشخیص تصویر در دنیا ۱۰۰٪ بدون خطا نیست.

در برخی تصاویر ممکن است نتیجه اشتباه تولید شود.

برای کاربردهای بسیار حساس، بررسی دستی نیز توصیه می‌شود.

نسخه‌های بعدی دقت بیشتری خواهند داشت.

---

# مجوز (MIT)

این پروژه تحت مجوز MIT منتشر شده است.

شما آزاد هستید:

- استفاده کنید.
- تغییر دهید.
- توسعه دهید.
- منتشر کنید.
- استفاده تجاری داشته باشید.
- فورک کنید.
- شخصی‌سازی کنید.

**تنها شرط استفاده، حفظ نام "Jibay" در پروژه، مستندات یا بخش اعتبار (Credits) است.**

---

# توسعه‌دهنده

ساخته شده با ❤️ توسط

# تیم جیبای

وبسایت رسمی:

https://jibay.ir
