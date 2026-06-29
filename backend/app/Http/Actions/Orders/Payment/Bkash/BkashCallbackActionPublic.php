<?php

namespace HiEvents\Http\Actions\Orders\Payment\Bkash;

use HiEvents\DomainObjects\BkashPaymentDomainObject;
use HiEvents\Exceptions\Bkash\BkashPaymentException;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\BkashPaymentRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\Payment\Bkash\BkashCallbackHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;

class BkashCallbackActionPublic extends BaseAction
{
    public function __construct(
        private readonly BkashCallbackHandler            $bkashCallbackHandler,
        private readonly BkashPaymentRepositoryInterface $bkashPaymentRepository,
        private readonly OrderRepositoryInterface        $orderRepository,
        private readonly LoggerInterface                 $logger,
    )
    {
    }

    /**
     * Handle GET redirect from bKash after payment completion/failure/cancellation.
     * bKash redirects the user's browser here with query params: status, paymentID.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $status = $request->query('status', '');
        $paymentID = $request->query('paymentID', '');

        $this->logger->info('bKash callback hit', [
            'status' => $status,
            'paymentID' => $paymentID,
            'all_params' => $request->all(),
        ]);

        if (empty($paymentID)) {
            return $this->redirectToFrontend(null, null, 'error', __('No payment ID received from bKash.'));
        }

        $bkashPayment = $this->bkashPaymentRepository->findFirstWhere([
            BkashPaymentDomainObject::PAYMENT_ID => $paymentID,
        ]);

        $eventId = null;
        $orderShortId = null;

        if ($bkashPayment) {
            $order = $this->orderRepository->findById($bkashPayment->getOrderId());
            if ($order) {
                $eventId = $order->getEventId();
                $orderShortId = $order->getShortId();
            }
        }

        try {
            $result = $this->bkashCallbackHandler->handle($status, $paymentID);

            if ($result->transactionStatus === 'Completed') {
                return $this->redirectToFrontend(
                    $eventId,
                    $orderShortId,
                    'success',
                    __('Payment completed successfully.')
                );
            }

            if ($result->transactionStatus === 'Initiated') {
                return $this->redirectToFrontend(
                    $eventId,
                    $orderShortId,
                    'pending',
                    __('Your payment is being processed. Please wait.')
                );
            }

            return $this->redirectToFrontend(
                $eventId,
                $orderShortId,
                'failed',
                __('Payment failed. Please try again.')
            );

        } catch (BkashPaymentException $e) {
            $this->logger->error('bKash callback error', [
                'error' => $e->getMessage(),
                'paymentID' => $paymentID,
            ]);
            return $this->redirectToFrontend(
                $eventId,
                $orderShortId,
                'error',
                $e->getMessage()
            );
        }
    }

    private function redirectToFrontend(
        ?int    $eventId,
        ?string $orderShortId,
        string  $status,
        string  $message
    ): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:8443');
        $params = [
            'status' => $status,
            'message' => $message,
        ];

        if ($eventId) {
            $params['event_id'] = $eventId;
        }
        if ($orderShortId) {
            $params['order_short_id'] = $orderShortId;
        }

        return redirect()->away($frontendUrl . '/checkout/bkash/return?' . http_build_query($params));
    }
}
