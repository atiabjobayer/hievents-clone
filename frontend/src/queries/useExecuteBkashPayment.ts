import {useMutation} from "@tanstack/react-query";
import {publicApi} from "../api/public-client.ts";

export const useExecuteBkashPayment = () => {
    return useMutation<
        { transactionStatus: string; trxID?: string; amount?: string; currency?: string },
        Error,
        { paymentID: string }
    >({
        mutationFn: ({paymentID}) => {
            return publicApi.post<{
                transactionStatus: string,
                trxID?: string,
                amount?: string,
                currency?: string,
            }>(`webhooks/bkash/execute`, {paymentID}).then((r: any) => r.data);
        }
    });
};
