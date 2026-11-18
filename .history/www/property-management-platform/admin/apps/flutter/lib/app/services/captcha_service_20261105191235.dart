// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
import 'dart:ui';
import 'package:dio/dio.dart';
import '../config/api_config.dart';

class CaptchaService {
  final Dio _dio;

  CaptchaService(this._dio);

  Future<CaptchaData> generate({String difficulty = 'medium'}) async {
    final resp = await _dio.post(ApiConfig.captchaGenerate, data: {
      'difficulty': difficulty,
    });
    if (resp.data['code'] != 0) throw Exception(resp.data['message']);
    return CaptchaData.fromJson(resp.data['data']);
  }

  Future<bool> verify(String key, List<Offset> clicks) async {
    final resp = await _dio.post(ApiConfig.captchaVerify, data: {
      'key': key,
      'clicks': clicks.map((c) => {'x': c.dx.round(), 'y': c.dy.round()}).toList(),
    });
    return resp.data['data']?['valid'] == true;
  }
}

class CaptchaData {
  final String key;
  final String imageBase64;
  final List<CaptchaTarget> targets;

  CaptchaData({required this.key, required this.imageBase64, required this.targets});

  factory CaptchaData.fromJson(Map<String, dynamic> json) {
    // 后端契约为 data.extra.texts（仅含 order/text，不下发真实坐标，防绕过）
    return CaptchaData(
      key: json['key'] as String,
      imageBase64: json['image'] as String,
      targets: (json['extra']?['texts'] as List?)
          ?.map((t) => CaptchaTarget.fromJson(t as Map<String, dynamic>))
          .toList() ?? [],
    );
  }
}

class CaptchaTarget {
  final int order;
  final String text;
  final int x;
  final int y;

  CaptchaTarget({required this.order, required this.text, required this.x, required this.y});

  factory CaptchaTarget.fromJson(Map<String, dynamic> json) {
    // texts 只有 order/text；x/y 不参与点击验证码前端逻辑，缺失时默认 0
    return CaptchaTarget(
      order: json['order'] as int,
      text: json['text'] as String,
      x: (json['x'] as int?) ?? 0,
      y: (json['y'] as int?) ?? 0,
    );
  }
}

