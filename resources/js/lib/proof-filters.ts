/**
 * Les filtres de signal de la file de preuves, appliques par le serveur
 * (`PaymentProofController::SignalFilters`). Une anomalie est un soupcon (reference ou capture deja
 * vues, ecart avec le releve) ; la precision de l'invite n'en est pas une, c'est une information a
 * lire. Les deux filtres restent distincts.
 */
export const ProofSignalFilters = ['all', 'anomaly', 'clean', 'note'] as const;

export type ProofSignalFilter = (typeof ProofSignalFilters)[number];
