export type EventReportUnit = {
    unit: string;
    confirmedRegistrations: number;
    presentRegistrations: number;
    presentSeats: number;
    collectedAmount: number;
};

export type EventReport = {
    confirmedRegistrations: number;
    confirmedSeats: number;
    presentRegistrations: number;
    presentSeats: number;
    absentRegistrations: number;
    absentSeats: number;
    collectedAmount: number;
    // README 2.11 : rembourse (frais compris), frais imputes, a rembourser, net.
    refundedAmount: number;
    refundFees: number;
    refundsDueAmount: number;
    netAmount: number;
    averageScanIntervalSeconds: number | null;
    units: EventReportUnit[];
};
