<?php

namespace AppBundle\Controller;

use PayPal\Api\Payment;
use Payum\Core\Gateway;
use Payum\Core\Payum;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Refund;
use Payum\Paypal\Rest\Model\PaymentDetails;
use Payum\Paypal\Rest\Model\RefundDetails;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Payum\Core\Request\Capture;

use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;

class DefaultController extends Controller
{

    /**
     * @Route("/done", name="done")
     */
    public function doneAction(Request $request)
    {
        /** @var Payum $payum */
        $payum = $this->get('payum');

        $token = $payum->getHttpRequestVerifier()->verify($request);

        $gateway = $payum->getGateway($token->getGatewayName());

        $gateway->execute($status = new GetHumanStatus($token));

        /** @var PaymentDetails $payment */
        $payment = $status->getFirstModel();

        return new JsonResponse(
            [
                'status' => $status->getValue(),
                'token'  => $token->getHash(),
            ]
        );
    }

    /**
     * @Route("/refund/{tokenHash}", name="refund")
     */
    public function refundAction($tokenHash, Request $request)
    {
        /** @var Payum $payum */
        $payum = $this->get('payum');

        $token = $payum->getTokenStorage()->find($tokenHash);

        $gateway = $payum->getGateway($token->getGatewayName());

        $gateway->execute($status = new GetHumanStatus($token));

        /** @var Payment $payment */
        $originalPayment = $status->getFirstModel();

        //Refund
        $refundToken = $payum->getTokenFactory()->createRefundToken('paypal', $originalPayment, 'done');

        return new RedirectResponse($refundToken->getTargetUrl());
    }

    /**
     * @Route("/rec", name="capture")
     */
    public function captureAction(Request $request)
    {
        /** @var Payum $payum */
        $payum = $this->get('payum');

        $token = $payum->getHttpRequestVerifier()->verify($request);

        $gateway = $payum->getGateway($token->getGatewayName());

        $gateway->execute($status = new GetHumanStatus($token));

        /** @var PaymentDetails $payment */
        $payment = $status->getFirstModel();

        return new JsonResponse(
            [
                'status' => $status->getValue(),
                'token'  => $token->getHash(),
            ]
        );
    }

    /**
     * @Route("/prepare", name="homepage")
     */
    public function prepareAction(Request $request)
    {
        /** @var Payum $payum */
        $payum = $this->get('payum');

        $paypalRestPaymentDetailsClass = PaymentDetails::class;

        $storage = $payum->getStorage($paypalRestPaymentDetailsClass);

        /** @var PaymentDetails $payment */
        $payment = $storage->create();
        $storage->update($payment);

        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $amount = new Amount();
        $amount->setCurrency('USD');
        $amount->setTotal('1.00');

        $transaction = new Transaction();
        $transaction->setAmount($amount);
        $transaction->setDescription('This is the payment description.');

        $captureToken = $payum->getTokenFactory()->createCaptureToken('paypal', $payment, 'capture');

        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($captureToken->getTargetUrl());
        $redirectUrls->setCancelUrl($captureToken->getTargetUrl());

        $payment->setIntent('sale');
        $payment->setPayer($payer);
        $payment->setRedirectUrls($redirectUrls);
        $payment->addTransaction($transaction);

        $storage->update($payment);

        return new RedirectResponse($captureToken->getTargetUrl());
    }
}
