<?php

class TourController extends Controller
{
    public function complete()
    {
        Auth::requireLogin();
        $this->onlyPost();
        User::markTourCompleted(Auth::id());
        $this->json(['ok' => true]);
    }
}
