# Algorigrammes – Planificateur FAMES 

Les diagrammes utilisent Mermaid. Dans VS Code, vous pouvez utiliser une extension Mermaid pour l'aperçu.

## Algorigrammes des fonctions

## clamp(x, lo, hi)
**Fonction utilitaire** : Borne une valeur entre un minimum et un maximum.
```mermaid
flowchart TD
  A([Début]) --> B[Entrée: x, lo, hi]
  B --> C["res = min(x, hi)"]
  C --> D["y = max(lo, res)"]
  D --> E[Sortie: y]
  E --> F([Fin])
```

## overlaps(a, b)
**Fonction utilitaire** : Vérifie si deux intervalles de temps se chevauchent.
```mermaid
flowchart TD
  A([Début]) --> B["Entrée: a.start, a.end, b.start, b.end"]
  B --> C{"a.start < b.end ET b.start < a.end ?"}
  C -- Oui --> D[Retour: VRAI]
  C -- Non --> E[Retour: FAUX]
  D --> F([Fin])
  E --> F
```

## add_busy(mapBusy, key, interval)
**Fonction utilitaire** : Ajoute un intervalle de temps occupé à la liste des créneaux indisponibles d'une entité (entreprise ou étudiant). Maintient la liste triée et fusionne les intervalles qui se chevauchent.
```mermaid
flowchart TD
  A([Début]) --> B{"key existe dans mapBusy ?"}
  B -- Non --> C["mapBusy[key] := liste vide"]
  B -- Oui --> D[Utiliser la liste existante]
  C --> E[Insérer interval en ordre croissant de début]
  D --> E
  E --> F[Fusionner intervalles qui se chevauchent]
  F --> G[Fin procédure]
  G --> H([Fin])
```

## next_free_within(fenêtre, durée, occupés)
**Fonction de planification** : Trouve le premier créneau libre de la durée souhaitée dans une fenêtre de temps, en évitant les créneaux déjà occupés.
```mermaid
flowchart TD
  A([Début]) --> B["Entrée: fenêtre=[fs,fe], durée, occupés"]
  B --> C["t := fs"]
  C --> D{"t + durée <= fe ?"}
  D -- Non --> E[Retour: null]
  D -- Oui --> F["candidat := [t, t+durée]"]
  F --> G{"candidat chevauche un occupé ?"}
  G -- Non --> H[Retour: candidat]
  G -- Oui --> I["ends := {b.end > t}"]
  I --> J{"ends vide ?"}
  J -- Oui --> E
  J -- Non --> K["t := min(ends)"]
  K --> D
```

## split_half_days(fenêtre)
**Fonction utilitaire** : Divise une fenêtre de temps en deux demi-journées (AM et PM) en respectant la pause déjeuner obligatoire de 12h30 à 13h30.
```mermaid
flowchart TD
  A([Début]) --> B["Entrée: fenêtre=[fs,fe]"]
  B --> C["Définir DÉJEUNER=12:30, REPRISE=13:30"]
  C --> D["AM := [fs, min(fe, 12:30)]"]
  D --> E["PM := [max(fs, 13:30), fe]"]
  E --> F["Si AM vide => AM=null"]
  F --> G["Si PM vide => PM=null"]
  G --> H["Retour: (AM, PM)"]
  H --> I([Fin])
```

## minutes(fenêtre)
**Fonction utilitaire** : Calcule la durée en minutes d'une fenêtre de temps donnée.
```mermaid
flowchart TD
  A([Début]) --> B["Entrée: fenêtre=[fs,fe]"]
  B --> C["delta := fe - fs"]
  C --> D["min := max(0, floor(delta en minutes))"]
  D --> E[Sortie: min]
  E --> F([Fin])
```

## compute_duration(dispoTotale, demande)
**Fonction de calcul** : Détermine la durée optimale des entretiens (entre 10 et 15 minutes) en fonction du temps disponible et du nombre de demandes.
```mermaid
flowchart TD
  A([Début]) --> B[Entrée: dispoTotale, demande]
  B --> C{"demande <= 0 ?"}
  C -- Oui --> D[Retour: 15]
  C -- Non --> E["est := floor(dispoTotale / demande)"]
  E --> F["durée := clamp(est, 10, 15)"]
  F --> G[Retour: durée]
  G --> H([Fin])
```

## insert_company_breaks(slots demi-journée, fenêtres, max=2, pause=10)
**Fonction de planification** : Insère automatiquement des pauses entreprise (max 2 par demi-journée, 10 min chacune) dans les créneaux disponibles en évitant les conflits avec les entretiens.
```mermaid
flowchart TD
  A([Début]) --> B["Copier slots -> result; trier par début"]
  B --> C["Calculer les gaps dans les fenêtres"]
  C --> D["breaks := 0"]
  D --> E{"breaks < max ?"}
  E -- Non --> F[Retour: result]
  E -- Oui --> G["Choisir gap (milieu/priorité au plus grand)"]
  G --> H{"gap existe ET taille >= pause ?"}
  H -- Non --> F
  H -- Oui --> I["bDébut := centre(gap) - pause/2"]
  I --> J["Insérer pause [bDébut, bDébut+10] dans result"]
  J --> K[Mettre à jour gaps]
  K --> L["breaks := breaks + 1"]
  L --> E
```

## Charger_Donnees(FORUM_ID)
**Fonction d'initialisation** : Charge toutes les données nécessaires depuis la base (forum, entreprises, disponibilités, demandes d'étudiants) et les structure pour la planification.
```mermaid
flowchart TD
  A([Début]) --> B[SELECT Forum]
  B --> C[SELECT Company]
  C --> D["SELECT Is_present_ (par forum)"]
  D --> E["SELECT Appointment (demandes) + User_"]
  E --> F["Scinder fenêtres en AM/PM"]
  F --> G[Construire availability_by_company]
  G --> H["Partitionner demandes: entreprise→type→niveau"]
  H --> I["Retour: forum, companies, availability, reqs"]
  I --> J([Fin])
```

## Calculer_Durees(companies, availability, reqs)
**Fonction de calcul** : Calcule la durée optimale des entretiens (10-15 min) pour chaque entreprise selon le temps disponible et le nombre de demandes étudiants.
```mermaid
flowchart TD
  A([Début]) --> B[Init duration_type_by_company]
  B --> C[Pour chaque entreprise]
  C --> D["Sommer minutes par type (AM+PM)"]
  D --> E[Compter demandes par type]
  E --> F["dur := compute_duration(total, demande)"]
  F --> G["Stocker {stage, alternance}"]
  G --> H{"Encore des entreprises ?"}
  H -- Oui --> C
  H -- Non --> I["Retour: durée par entreprise/type"]
  I --> J([Fin])
```

## Placer_RendezVous(companies, availability, reqs, durations)
**Fonction principale de planification** : Place les rendez-vous en respectant l'ordre stage→alternance, les niveaux croissants, les disponibilités des entreprises et évite les conflits d'horaires.
```mermaid
flowchart TD
  A([Début]) --> B["Init busy entreprise/étudiant, planning, liste d'attente"]
  B --> C[Pour chaque entreprise]
  C --> D["Pour chaque demi-journée (AM, PM)"]
  D --> E["windows := dispo[entreprise][hd] triées"]
  E --> F["Pour type dans (stage, alternance)"]
  F --> G[Pour niveaux croissants]
  G --> H[Pour chaque étudiant]
  H --> I["dur := durations[entreprise][type]"]
  I --> J{"Chercher un créneau via next_free_within"}
  J -- Trouvé --> K["Ajouter Slot; MAJ busy"]
  J -- Non --> L[Mettre en attente]
  K --> M{"Encore des étudiants ?"}
  L --> M
  M -- Oui --> H
  M -- Non --> N["Insérer ≤2 pauses dans la demi-journée"]
  N --> O{"Encore des demi-journées ?"}
  O -- Oui --> D
  O -- Non --> P{"Encore des entreprises ?"}
  P -- Oui --> C
  P -- Non --> Q["Retour: planning + busy + waitlist"]
  Q --> R([Fin])
```

## Compacter_Attente_Etudiants(planning, busy entreprises/étudiants, availability)
**Fonction d'optimisation** : Réduit les temps d'attente des étudiants en regroupant leurs entretiens et en évitant les grands trous dans leur emploi du temps.
```mermaid
flowchart TD
  A([Début]) --> B[Regrouper les slots par étudiant]
  B --> C["Pour chaque étudiant: 2 passes"]
  C --> D["Pour chaque paire adjacente s1,s2"]
  D --> E["gap := s2.start - s1.end"]
  E --> F{"gap <= objectif ?"}
  F -- Oui --> D
  F -- Non --> G["Essayer d'avancer s2 (pull-forward)"]
  G --> H{"Valide et sans conflit ?"}
  H -- Oui --> I[Appliquer le décalage]
  H -- Non --> J["Essayer de reculer s1 (push-back)"]
  I --> K[Prochaine paire]
  J --> L{"Valide et sans conflit ?"}
  L -- Oui --> I
  L -- Non --> K
  K --> M["Option: petit échange dans même entreprise/type"]
  M --> N[Prochain étudiant]
  N --> O([Fin])
```

## Ecrire_En_Base(FORUM_ID, planning, liste d'attente)
**Fonction de persistance** : Écrit les rendez-vous planifiés et les pauses dans la base de données et marque les demandes non satisfaites en liste d'attente.
```mermaid
flowchart TD
  A([Début]) --> B[Pour chaque slot par heure]
  B --> C{slot.type == 'interview' ?}
  C -- Oui --> D[UPDATE du rendez-vous existant]
  D --> E{0 ligne MAJ ?}
  E -- Oui --> F[INSERT nouveau rendez-vous]
  E -- Non --> G[Continuer]
  C -- Non --> H[INSERT ligne de pause]
  F --> G
  H --> G
  G --> I[Marquer la liste d'attente en WAITLIST]
  I --> J([Fin])
```


## Planifier_FAMES(FORUM_ID)
**Fonction principale orchestratrice** : Coordonne toutes les étapes de la planification automatique des emplois du temps FAMES en appelant séquentiellement les autres fonctions.
```mermaid
flowchart TD
  A([Début]) --> B[Charger_Donnees]
  B --> C[Calculer_Durees]
  C --> D[Placer_RendezVous]
  D --> E[Compacter_Attente_Etudiants]
  E --> G[Ecrire_En_Base]
  G --> H["Retour: OK"]
  H --> I([Fin])
```

