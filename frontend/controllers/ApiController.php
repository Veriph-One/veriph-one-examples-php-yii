<?php

namespace frontend\controllers;

use Yii;
use yii\rest\Controller;
use yii\httpclient\Client;
use yii\helpers\Json;

/**
 * API controller
 */
class ApiController extends Controller
{
    // Replace this with your API Key & secret, remember to key these values accesible to your server only.
    // Never expose this value to client-side code. Also, use environment variables or secrets
    // that keep the value from being committed to a repository.

    const VERIPH_ONE_API_KEY = "";
    const VERIPH_ONE_API_KEY_SECRET = "";

    private function createVerification($prefilledNumber = NULL)
    {
        $request = Yii::$app->request;
        
        // Check if the request method is GET
        if ($request->isGet) {
            // Capture user-agent header for the metadata object
            $userAgent = $request->headers->get('User-Agent');
            // Capture client IP address for the metadata object
            $clientIp = $request->getUserIP();

            if (!$userAgent) {
                return $this->asJson(['error' => 'Bad Request']);
            }

            try {
                // This userId should be linked to some user-related object in your DB.
                // It allows you to identify usage of the same number across multiple accounts.
                // This example uses a random value for simplicity sake.
                $userId = uniqid();
                
                $sessionMetadata = [
                    'userId' => $userId,
                    'ipAddress' => $clientIp,
                    'userAgent' => $userAgent,
                ];

                $apiKey = self::VERIPH_ONE_API_KEY;

                if (empty($apiKey)) {
                    $errorMessage = "API Key is empty, get yours at dashboard.veriph.one";
                    Yii::error($errorMessage, __METHOD__);
                    Yii::$app->session->setFlash('error', $errorMessage);
                    return $this->redirect('/', 307);
                }

                // Create a verification session by making a request to the Veriph.One API
                $client = new Client();
                $response = $client->createRequest()
                    ->setMethod('POST')
                    ->setUrl('https://service.veriph.one/sdk-api/phone-verification/create-session/v1.0.0')
                    ->addHeaders(['Content-Type' => 'application/json', 'x-api-key' => $apiKey])
                    ->setData([
                        'metadata' => $sessionMetadata,
                        'configuration' => ['locale' => 'es'],
                        'prefilledPhoneNumber' => $prefilledNumber,
                    ])
                    ->send();

                if ($response->isOk) {
                    // Verification session was created successfully. In a real scenario, you'd
                    // save the sessionUuid to your database to be able to identify which verification
                    // result is linked to which transaction and user.
                    // After doing so, we redirect the user to Veriph.One's SDK to perform the
                    // verification flow.
                    $data = Json::decode($response->content);
                    return $this->redirect($data['redirectionUrl'], 307);
                } else {
                    // If something goes wrong, we return to the last page and show an error
                    $errorMessage = $response->content;
                    Yii::error($errorMessage, __METHOD__);
                    Yii::$app->session->setFlash('error', "Something went wrong while capturing your phone number. Error: {$errorMessage}");
                    return $this->redirect('/', 307);
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                Yii::error($errorMessage, __METHOD__);
                Yii::$app->session->setFlash('error', "Sorry, we are unable to capture your phone number. Error: {$errorMessage}");
                // Remember this is a request made by the user's browser, so you should always redirect to some place with UI.
                return $this->redirect('/', 307);
            }
        } else {
            // We return a JSON here because it is unlikely that a browser is making a non-GET request.
            return $this->asJson(['error' => 'Method Not Allowed']);
        }
    }

    public function actionSignUp()
    {
        self::createVerification();
    }

    public function actionAuth()
    {
        $prefilledPhoneNumber = [
            // Place the country code of the number you want to verify here
            "countryCode" => "52",
            // Place the number you want to verify here
            "cellphoneNumber" => "5539017050",
        ];
        self::createVerification($prefilledPhoneNumber);
    }

    /**
     * This endpoint processes the verification results and is reused for both sign-up and auth flows.
     * Please note that a real-life example wouldn't do so. You can create more than one API Key for
     * each flow if you would like to have multiple verification behaviours and set different URLs to
     * receive each result.
     * @return Yii\web\Response
     */
    public function actionVerificationResult()
    {
        $request = Yii::$app->request;
        
        if ($request->isGet) {
            $sessionUuid = $request->getQueryParam('sessionUuid');
            
            if (!$sessionUuid) {
                return $this->asJson(['error' => 'Bad Request']);
            }

            try {
                $apiKey = self::VERIPH_ONE_API_KEY;
                $secret = self::VERIPH_ONE_API_KEY_SECRET;

                if (empty($apiKey) || empty($secret)) {
                    $errorMessage = "API Key/Secret is empty, get yours at dashboard.veriph.one";
                    Yii::error($errorMessage, __METHOD__);
                    Yii::$app->session->setFlash('error', $errorMessage);
                    return $this->redirect('/', 307);
                }

                $client = new Client();
                $response = $client->createRequest()
                    ->setMethod('GET')
                    ->setUrl('https://service.veriph.one/sdk-api/phone-verification/verification-result/v1.0.0')
                    ->addHeaders([
                        'Content-Type' => 'application/json',
                        'x-api-key' => $apiKey,
                        'Authorization' => "Basic {$secret}",
                    ])
                    ->setData(['sessionUuid' => $sessionUuid])
                    ->send();

                if ($response->isOk) {
                    $verificationResult = Json::decode($response->content);

                    // If the firstSuccessfulAttempt object is included, the verification was successful.
                    if (isset($verificationResult['firstSuccessfulAttempt'])) {
                        // Under non-example conditions, this is where your server's magic would happen.
                        // You can create an account, perform a sensitive operation, or let the user continue
                        // with the expected flow. In this example, we just return the phone number so that
                        // the UI can show it.
                        $countryCode = $verificationResult['firstSuccessfulAttempt']['countryCodeInput'];
                        $verifiedNumber = $verificationResult['firstSuccessfulAttempt']['phoneNumberInput'];
                        $fullNumber = "+{$countryCode}{$verifiedNumber}";

                        // When working with webapps and websites, the recommended approach involves using
                        // redirections and query parameters to communicate the next steps to the UI.
                        Yii::$app->session->setFlash('success', "Your phone ({$fullNumber}) has been verified!");
                        // For simplicity sake we're redirecting to the index page, but you could lead the user to /home or similar after a sign up process
                        return $this->redirect('/', 307);
                    } else {
                        // We recommend that if your verification is unsuccessful, you use the list of attempts included in the payload to understand
                        // what has gone wrong. This will allow you to give feedback to the user and avoid unnecessary friction with customer support.
                        // See this section of our documentation on how to do so: https://developer.veriph.one/docs/server/result-endpoint#parsing-the-status
                        // For simplicity sake we're redirecting to the index page, but you could lead the user to /error or similar to show an error
                        Yii::error($verificationResult, __METHOD__);
                        Yii::$app->session->setFlash('error', "Your phone number verification failed! Please ensure you follow the verification instructions and try again.");
                        return $this->redirect('/', 307);
                    }
                } else {
                    // If something goes wrong, we return to the last page and show an error
                    $errorMessage = $response->content;
                    Yii::error($errorMessage, __METHOD__);
                    Yii::$app->session->setFlash('error', "Something went wrong while verifying your phone number. Error: {$errorMessage}");
                    return $this->redirect('/', 307);
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                Yii::error($errorMessage, __METHOD__);
                Yii::$app->session->setFlash('error', "Sorry, we were unable to process the verification result. Error: {$errorMessage}");
                // Remember this is a request made by the user's browser, so you should always redirect to some place with UI.
                return $this->redirect('/', 307);
            }
        } else {
            // We return a JSON here because it is unlikely that a browser is making a non-GET request.
            return $this->asJson(['error' => 'Method Not Allowed']);
        }
    }
}
