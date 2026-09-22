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
    averageScanIntervalSeconds: number | null;
    units: EventReportUnit[];
};
