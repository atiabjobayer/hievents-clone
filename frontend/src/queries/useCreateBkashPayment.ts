import {useMutation} from "@tanstack/react-query";
import {orderClientPublic} from "../api/order.client.ts";
import {IdParam} from "../types.ts";

export const useCreateBkashPayment = () => {
    return useMutation<
        { payment_id: string; merchant_invoice_number: string; transaction_status: string; hash: string },
        Error,
        { eventId: IdParam; orderShortId: IdParam }
    >({
        mutationFn: ({eventId, orderShortId}) => {
            return orderClientPublic.createBkashPayment(Number(eventId), String(orderShortId));
        }
    });
};
