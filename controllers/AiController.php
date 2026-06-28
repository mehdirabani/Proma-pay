<?php

class AiController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $this->render('ai/index', ['title' => 'تحلیل هوشمند متن', 'analysis' => null]);
    }

    public function analyze()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $settings = Settings::allKeyed();
        $client = new OpenRouterClient($settings['openrouter_api_key'], $settings['openrouter_model']);
        $result = $client->analyze($_POST['text'] ?? '', 'شما یک تحلیل‌گر مالی فارسی هستید. متن ورودی را به شکل ساختارمند با بخش‌های خلاصه، ریسک‌ها، اقدام‌های پیشنهادی و نکات حقوقی پاسخ دهید.');
        $this->render('ai/index', [
            'title' => 'تحلیل هوشمند متن',
            'analysis' => $result,
            'text' => $_POST['text'] ?? '',
        ]);
    }
}
