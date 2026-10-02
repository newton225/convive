// Une section d'un document juridique : des paragraphes, une liste, puis des paragraphes de fin.
// `id` sert d'ancre au sommaire.
export type LegalSection = {
    id: string;
    title: string;
    paragraphs: string[];
    items: string[];
    after: string[];
};

// Un document juridique du site, deja traduit et complete par le serveur.
export type LegalDocument = {
    slug: 'privacy' | 'terms' | 'notice';
    title: string;
    summary: string;
    sections: LegalSection[];
};

// Les libelles de la page, lus cote serveur : le groupe `legal` n'est pas envoye avec les
// traductions partagees.
export type LegalLabels = {
    updated: string;
    contents: string;
    notReviewed: string;
    nav: {
        title: string;
        privacy: string;
        terms: string;
        notice: string;
    };
};
