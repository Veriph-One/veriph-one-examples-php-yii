<?php

/** @var yii\web\View $this */

$this->title = 'Veriph.One PHP + Yii Integration Example';
?>
<div class="site-index">
    <div class="p-5 bg-transparent rounded-3">
        <div class="container-fluid py-5 text-center">
            <h1 class="display-4">Phone-based verification example</h1>
            <p class="fs-5 fw-light">This example uses PHP, Yii and Veriph.One to sign up or perform MFA using a phone number. It has an MVC architecture, but you can also implement it using an API.</p>

            <p>Remember to set your API Key and Secret by editing&nbsp;
                <code>frontend/models/PhoneVerification.php</code>
            </p>

            <p><a class="btn btn-lg btn-success" href="https://developer.veriph.one">Visit our developer website</a></p>
        </div>
    </div>

    <div class="body-content">

        <div class="row">
            <div class="col-lg-6">
                <h2>Sign up flow</h2>

                <p>A simple flow to capture a verified phone number safely for the first time, usually helpful for sign up, KYC, and onboarding processes. Can also be used for login flows in your app.</p>

                <p><a class="btn btn-outline-secondary" href="/sign-up">Go to verification &raquo;</a></p>
            </div>
            <div class="col-lg-6">
                <h2>MFA flow</h2>

                <p>A process to verify a specific phone number (determined by your server) to execute a transaction. It can be used for post-login MFA or when executing sensitive operations.</p>

                <p><a class="btn btn-outline-secondary" href="/mfa">Go to verification &raquo;</a></p>
            </div>
        </div>

    </div>
</div>
