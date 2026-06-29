import {useCallback, useEffect, useState} from "react";
import {useParams} from "react-router";
import {t} from "@lingui/macro";
import {Button, Text} from "@mantine/core";
import {IconWallet} from "@tabler/icons-react";
import {useCreateBkashPayment} from "../../../../../../queries/useCreateBkashPayment.ts";
import {useExecuteBkashPayment} from "../../../../../../queries/useExecuteBkashPayment.ts";
import {showError, showSuccess} from "../../../../../../utilites/notifications.tsx";
import classes from "./BkashPaymentMethod.module.scss";

declare global {
    interface Window {
        bKash: any;
        paymentID?: string;
    }
}

const SCRIPT_ID = 'bkash-checkout-script';
const SCRIPT_URL = 'https://scripts.sandbox.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout-sandbox.js';

export const BkashPaymentMethod = ({enabled}: { enabled: boolean }) => {
    const {eventId, orderShortId} = useParams();
    const createBkashPayment = useCreateBkashPayment();
    const executeBkashPayment = useExecuteBkashPayment();
    const [isReady, setIsReady] = useState(false);
    const [attemptKey, setAttemptKey] = useState(0);

    const resetBkash = useCallback(() => {
        // Defer reset to next tick so bKash can finish its internal onError() cleanup first
        setTimeout(() => {
            const existingScript = document.getElementById(SCRIPT_ID);
            if (existingScript) existingScript.remove();
            delete (window as any).bKash;
            delete window.paymentID;
            setIsReady(false);
            setAttemptKey(k => k + 1);
        }, 100);
    }, []);

    useEffect(() => {
        if (!enabled) return;

        setIsReady(false);
        delete window.paymentID;

        const existingScript = document.getElementById(SCRIPT_ID);
        if (existingScript) existingScript.remove();
        delete (window as any).bKash;

        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        script.src = SCRIPT_URL;
        script.onload = () => {
            if (!window.bKash) {
                showError(t`bKash failed to load. Please refresh the page.`);
                return;
            }

            window.bKash.init({
                paymentMode: 'checkout',
                paymentRequest: {},
                createRequest: async (_request: any) => {
                    try {
                        const result = await createBkashPayment.mutateAsync({
                            eventId: Number(eventId),
                            orderShortId: String(orderShortId),
                        });

                        if (result.payment_id) {
                            window.paymentID = result.payment_id;
                            // Pass the FULL create response (including hash) — bKash needs it for page validation
                            window.bKash.create().onSuccess({
                                paymentID: result.payment_id,
                                hash: result.hash,
                                merchantInvoiceNumber: result.merchant_invoice_number,
                                transactionStatus: result.transaction_status,
                            });
                        } else {
                            window.bKash.create().onError();
                            showError(t`Failed to create bKash payment.`);
                            resetBkash();
                        }
                    } catch (error: any) {
                        window.bKash.create().onError();
                        showError(error?.response?.data?.message || t`Failed to create bKash payment.`);
                        resetBkash();
                    }
                },
                executeRequestOnAuthorization: async () => {
                    const pid = window.paymentID;
                    if (!pid) {
                        window.bKash.execute().onError();
                        showError(t`Payment ID not found.`);
                        resetBkash();
                        return;
                    }

                    // Show immediate feedback — the execute API can take 1-5 seconds
                    showSuccess(t`Verification successful! Processing payment, please wait...`);

                    try {
                        const result = await executeBkashPayment.mutateAsync({
                            paymentID: pid,
                        });

                        if (result.transactionStatus === 'Completed') {
                            // Use full page navigation (like the demo) instead of React Router
                            // navigate() — the bKash popup DOM interferes with React's tree
                            window.location.href = `/checkout/${eventId}/${orderShortId}/summary`;
                        } else {
                            window.bKash.execute().onError();
                            showError(t`Payment was not completed. Please try again.`);
                            resetBkash();
                        }
                    } catch (error: any) {
                        window.bKash.execute().onError();
                        showError(error?.response?.data?.message || t`bKash payment execution failed.`);
                        resetBkash();
                    }
                },
            });

            setIsReady(true);
        };
        script.onerror = () => {
            showError(t`Failed to load bKash. Please refresh and try again.`);
        };
        document.head.appendChild(script);

        return () => {
            const s = document.getElementById(SCRIPT_ID);
            if (s) s.remove();
            delete (window as any).bKash;
        };
    }, [enabled, eventId, orderShortId, attemptKey]);

    if (!enabled) return null;

    return (
        <div className={classes.container}>
            <div className={classes.bkashInfo}>
                <IconWallet size={48} stroke={1.5} className={classes.icon}/>
                <Text size="lg" fw={600} mt="md">{t`Pay with bKash`}</Text>
                <Text size="sm" c="dimmed" mt="xs">
                    {t`Complete your payment securely using your bKash wallet.`}
                </Text>
            </div>
            <Button
                id="bKash_button"
                size="lg"
                fullWidth
                className={classes.payButton}
                disabled={!isReady}
                loading={!isReady || createBkashPayment.isPending || executeBkashPayment.isPending}
            >
                {isReady ? t`Pay with bKash` : t`Loading bKash...`}
            </Button>
        </div>
    );
};
