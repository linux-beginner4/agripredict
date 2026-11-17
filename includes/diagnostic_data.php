<?php
/**
 * Base de connaissances de l'aide à l'orientation sanitaire (VOLAILLE).
 *
 * ⚠ VERSION DE TRAVAIL, À FAIRE RELIRE PAR UN VÉTÉRINAIRE avant tout usage réel.
 * Les poids (1 à 3) indiquent à quel point un symptôme est évocateur d'une affection (3 = très évocateur).
 * Ce fichier est volontairement séparé du code : un vétérinaire peut le relire et le corriger sans toucher au reste.
 * L'outil ne prescrit aucun traitement et ne remplace pas un vétérinaire.
 */

const DIAG_BASE_VERSION = 'v0 : non validée par un vétérinaire';

/** Symptômes proposés, par groupe : identifiant => libellé */
const SYMPTOMES = [
    'Respiration' => [
        'toux'      => 'Toux, éternuements',
        'rales'     => 'Respiration bruyante, râles',
        'ecoulement' => 'Écoulement du nez ou des yeux',
        'halete'    => 'Bec ouvert, halètement, ailes écartées',
    ],
    'Digestion' => [
        'diarrhee_verte'  => 'Diarrhée verdâtre',
        'diarrhee_blanche' => 'Diarrhée blanchâtre ou très liquide',
        'diarrhee_sang'   => 'Diarrhée avec du sang ou brunâtre',
        'appetit'         => "Perte d'appétit",
        'soif'            => 'Soif excessive',
    ],
    'État général' => [
        'abattement' => 'Abattement, sujets prostrés',
        'plumes'     => 'Plumes ébouriffées',
        'maigreur'   => 'Amaigrissement ou croissance ralentie',
        'mortalite'  => 'Mortalité anormale ou mort subite',
    ],
    'Nerveux et mouvement' => [
        'torticolis' => 'Torticolis, tremblements, tête tordue',
        'paralysie'  => 'Paralysie des pattes ou des ailes',
        'boiterie'   => 'Boiterie, articulations gonflées',
    ],
    'Tête et peau' => [
        'cyanose'        => 'Crête ou barbillons bleuâtres ou violacés',
        'gonflement_tete' => 'Gonflement de la tête ou de la face',
        'croutes'        => 'Boutons ou croûtes sur la crête ou les barbillons',
        'crete_pale'     => 'Crête pâle',
        'grattage'       => 'Grattage, plumes abîmées, parasites visibles',
    ],
    'Ponte' => [
        'baisse_ponte'   => 'Baisse de ponte',
        'oeufs_deformes' => 'Œufs déformés ou à coquille fine',
    ],
];

/** Part d'animaux touchés */
const DIAG_TOUCHES = [
    'quelques'     => 'Quelques sujets seulement',
    'beaucoup'     => 'Beaucoup de sujets (plus de 1 sur 10)',
    'presque_tous' => 'Presque tout le lot',
];

/**
 * Affections : nom, gravité (moderee | grave | critique), symptômes pondérés,
 * isolement conseillé, déclaration aux services vétérinaires, mot-clé du vaccin associé, conseil.
 */
const MALADIES = [
    'newcastle' => [
        'nom' => 'Maladie de Newcastle',
        'gravite' => 'critique',
        'symptomes' => ['toux' => 2, 'rales' => 2, 'diarrhee_verte' => 2, 'torticolis' => 3, 'paralysie' => 2,
                        'baisse_ponte' => 2, 'oeufs_deformes' => 2, 'abattement' => 1, 'appetit' => 1, 'mortalite' => 2],
        'isolement' => true,
        'declaration' => true,
        'vaccin' => 'Newcastle',
        'conseil' => "Maladie très contagieuse. Isolez immédiatement les sujets atteints, limitez les déplacements de personnes et de matériel entre les bâtiments, ne vendez et ne déplacez aucun animal. Appelez un vétérinaire sans attendre.",
    ],
    'grippe_aviaire' => [
        'nom' => 'Grippe aviaire',
        'gravite' => 'critique',
        'symptomes' => ['mortalite' => 3, 'cyanose' => 3, 'gonflement_tete' => 3, 'baisse_ponte' => 2, 'abattement' => 2,
                        'diarrhee_verte' => 1, 'rales' => 1, 'appetit' => 1],
        'isolement' => true,
        'declaration' => true,
        'vaccin' => null,
        'conseil' => "Peut, dans certains cas, se transmettre à l'homme : évitez tout contact direct sans protection (gants, masque, bottes), ne déplacez aucun animal, ne vendez rien et prévenez immédiatement un vétérinaire ou les services vétérinaires.",
    ],
    'gumboro' => [
        'nom' => 'Maladie de Gumboro',
        'gravite' => 'grave',
        'symptomes' => ['diarrhee_blanche' => 3, 'plumes' => 2, 'abattement' => 2, 'appetit' => 1, 'mortalite' => 2, 'soif' => 1],
        'isolement' => true,
        'declaration' => false,
        'vaccin' => 'Gumboro',
        'conseil' => "Touche surtout les jeunes poulets (environ 3 à 6 semaines). Gardez l'eau et l'aliment accessibles, évitez tout stress, et faites confirmer par un vétérinaire.",
    ],
    'bronchite' => [
        'nom' => 'Bronchite infectieuse',
        'gravite' => 'grave',
        'symptomes' => ['toux' => 3, 'rales' => 3, 'ecoulement' => 2, 'baisse_ponte' => 2, 'oeufs_deformes' => 3],
        'isolement' => true,
        'declaration' => false,
        'vaccin' => 'Bronchite',
        'conseil' => "Réduisez les courants d'air et la poussière, vérifiez la ventilation et demandez l'avis d'un vétérinaire : les infections secondaires sont fréquentes.",
    ],
    'coccidiose' => [
        'nom' => 'Coccidiose',
        'gravite' => 'moderee',
        'symptomes' => ['diarrhee_sang' => 3, 'plumes' => 2, 'abattement' => 2, 'appetit' => 1, 'maigreur' => 2, 'crete_pale' => 1],
        'isolement' => false,
        'declaration' => false,
        'vaccin' => null,
        'conseil' => "Favorisée par une litière humide : vérifiez l'état et l'humidité de la litière. Un vétérinaire peut confirmer et prescrire un traitement adapté.",
    ],
    'coryza' => [
        'nom' => 'Coryza infectieux',
        'gravite' => 'moderee',
        'symptomes' => ['gonflement_tete' => 3, 'ecoulement' => 3, 'toux' => 1, 'baisse_ponte' => 1, 'appetit' => 1],
        'isolement' => true,
        'declaration' => false,
        'vaccin' => null,
        'conseil' => "N'introduisez pas de nouveaux sujets sans contrôle, améliorez la ventilation et demandez un diagnostic vétérinaire.",
    ],
    'pasteurellose' => [
        'nom' => 'Pasteurellose (choléra aviaire)',
        'gravite' => 'grave',
        'symptomes' => ['mortalite' => 3, 'cyanose' => 2, 'diarrhee_verte' => 2, 'boiterie' => 1, 'abattement' => 1, 'appetit' => 1],
        'isolement' => true,
        'declaration' => false,
        'vaccin' => null,
        'conseil' => "L'évolution peut être très rapide. Séparez les sujets malades, nettoyez l'eau de boisson et le matériel, et appelez un vétérinaire.",
    ],
    'salmonellose' => [
        'nom' => 'Pullorose / salmonellose',
        'gravite' => 'grave',
        'symptomes' => ['diarrhee_blanche' => 3, 'abattement' => 2, 'mortalite' => 2, 'appetit' => 1, 'plumes' => 1],
        'isolement' => true,
        'declaration' => false,
        'vaccin' => null,
        'conseil' => "Touche surtout les poussins. Isolez les sujets atteints et soyez très rigoureux sur l'hygiène des mains et du matériel (risque pour l'homme). Faites confirmer par un vétérinaire.",
    ],
    'variole' => [
        'nom' => 'Variole aviaire',
        'gravite' => 'moderee',
        'symptomes' => ['croutes' => 3, 'baisse_ponte' => 1, 'appetit' => 1, 'rales' => 1],
        'isolement' => true,
        'declaration' => false,
        'vaccin' => 'Variole',
        'conseil' => "Séparez les sujets présentant des lésions et limitez les moustiques autour des bâtiments. Faites confirmer par un vétérinaire.",
    ],
    'marek' => [
        'nom' => 'Maladie de Marek',
        'gravite' => 'grave',
        'symptomes' => ['paralysie' => 3, 'maigreur' => 2, 'boiterie' => 1, 'abattement' => 1, 'crete_pale' => 1],
        'isolement' => true,
        'declaration' => false,
        'vaccin' => 'Marek',
        'conseil' => "Les sujets atteints ne guérissent généralement pas ; la prévention passe par la vaccination des poussins (souvent faite au couvoir). Demandez l'avis d'un vétérinaire sur la conduite à tenir pour le lot.",
    ],
    'chaleur' => [
        'nom' => 'Coup de chaleur (non infectieux)',
        'gravite' => 'moderee',
        'symptomes' => ['halete' => 3, 'abattement' => 2, 'soif' => 2, 'baisse_ponte' => 2, 'mortalite' => 1, 'appetit' => 1],
        'isolement' => false,
        'declaration' => false,
        'vaccin' => null,
        'conseil' => "Ce n'est pas une maladie contagieuse : assurez de l'ombre, une bonne ventilation et de l'eau fraîche en permanence, et évitez de manipuler les oiseaux aux heures chaudes.",
    ],
    'parasites' => [
        'nom' => 'Parasites externes (poux, acariens)',
        'gravite' => 'moderee',
        'symptomes' => ['grattage' => 3, 'crete_pale' => 2, 'plumes' => 1, 'baisse_ponte' => 1, 'maigreur' => 1],
        'isolement' => false,
        'declaration' => false,
        'vaccin' => null,
        'conseil' => "Inspectez le dessous des ailes, le cloaque et les perchoirs. Nettoyez et désinfectez le bâtiment, puis demandez conseil pour un traitement adapté.",
    ],
];
