import {useEffect, useRef} from "react";
import {useNavigate, useSearchParams} from "react-router";
import {t} from "@lingui/macro";
import {CheckoutContent} from "../../../layouts/Checkout/CheckoutContent";
import {HomepageInfoMessage} from "../../../common/HomepageInfoMessage";
import {eventCheckoutPath} from "../../../../utilites/urlHelper.ts";
import {trackEvent, AnalyticsEvents} from "../../../../utilites/analytics.ts";

/**
 * Handles the return from bKash after payment completion/failure/cancellation.
 * The backend callback redirects the user here with query params:
 *   status, message, event_id, order_short_id
 */
export const BkashReturn = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const status = searchParams.get('status');
    const message = searchParams.get('message');
    const eventId = searchParams.get('event_id');
    const orderShortId = searchParams.get('order_short_id');
    const hasTracked = useRef(false);

    useEffect(() => {
        if (status === 'success' && eventId && orderShortId) {
            if (!hasTracked.current) {
                hasTracked.current = true;
                trackEvent(AnalyticsEvents.PURCHASE_COMPLETED_PAID, { value: 0 });
            }
            navigate(eventCheckoutPath(eventId, orderShortId, 'summary'));
            return;
        }

        if ((status === 'failed' || status === 'error') && eventId && orderShortId) {
            navigate(eventCheckoutPath(eventId, orderShortId, 'payment') + '?payment_failed=true');
            return;
        }

        if (status === 'cancel' && eventId && orderShortId) {
            navigate(eventCheckoutPath(eventId, orderShortId, 'payment') + '?payment_cancelled=true');
            return;
        }
    }, [status, eventId, orderShortId, navigate]);

    if (status === 'success') {
        return (
            <CheckoutContent>
                <HomepageInfoMessage
                    status="success"
                    message={t`Payment Successful`}
                    subtitle={message || t`Your bKash payment has been processed. Redirecting...`}
                />
            </CheckoutContent>
        );
    }

    if (status === 'pending') {
        return (
            <CheckoutContent>
                <HomepageInfoMessage
                    status="info"
                    message={t`Payment Processing`}
                    subtitle={message || t`Your bKash payment is being processed. Please check your order status shortly.`}
                />
            </CheckoutContent>
        );
    }

    if (status === 'failed' || status === 'error') {
        return (
            <CheckoutContent>
                <HomepageInfoMessage
                    status="error"
                    message={t`Payment Failed`}
                    subtitle={message || t`Your bKash payment could not be processed. Please try again.`}
                />
            </CheckoutContent>
        );
    }

    if (status === 'cancel') {
        return (
            <CheckoutContent>
                <HomepageInfoMessage
                    status="warning"
                    message={t`Payment Cancelled`}
                    subtitle={message || t`You cancelled the bKash payment. You can try again.`}
                />
            </CheckoutContent>
        );
    }

    return (
        <CheckoutContent>
            <HomepageInfoMessage
                status="info"
                message={t`Processing Payment`}
                subtitle={t`Please wait while we confirm your payment status...`}
            />
        </CheckoutContent>
    );
};

export default BkashReturn;
