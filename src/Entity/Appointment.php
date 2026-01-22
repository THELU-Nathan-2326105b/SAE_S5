<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Appointment Entity
 *
 * Représente un rendez-vous entre un utilisateur (étudiant) et une entreprise lors d'un forum.
 * Utilise une clé primaire composite (user_id, forum_id, company_name).
 *
 * @package App\Entity
 */
#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Table(name: "appointment")]
class Appointment
{
    /**
     * @var Users Utilisateur associé au rendez-vous
     */
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "user_id")]
    private Users $user;

    /**
     * @var Forum Forum associé au rendez-vous
     */
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(name: "forum_id", referencedColumnName: "forum_id")]
    private Forum $forum;

    /**
     * @var string Nom de l'entreprise (max 100 caractères)
     */
    #[ORM\Id]
    #[ORM\Column(name: "company_name", type: "string", length: 100)]
    private string $companyName;

    /**
     * @var bool Indique si c'est une demande de rendez-vous
     */
    #[ORM\Column(name: "appointment_request", type: "boolean")]
    private bool $appointmentRequest;

    /**
     * @var ?\DateTimeInterface Heure du rendez-vous (peut être null si non attribué)
     */
    #[ORM\Column(name: "appointment_time", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $appointmentTime = null;

    /**
     * @var int Durée du rendez-vous en minutes
     */
    #[ORM\Column(name: "duration", type: "integer")]
    private int $duration = 0;

    // --------------------
    // Getters & Setters
    // --------------------

    /**
     * Récupère l'utilisateur associé au rendez-vous
     *
     * @return Users L'utilisateur/étudiant
     */
    public function getUser(): Users {
        return $this->user;
    }

    /**
     * Définit l'utilisateur du rendez-vous
     *
     * @param Users $user L'utilisateur/étudiant
     * @return self Instance courante pour le chaînage
     */
    public function setUser(Users $user): self {
        $this->user = $user;
        return $this;
    }

    /**
     * Récupère le forum associé au rendez-vous
     *
     * @return Forum Le forum
     */
    public function getForum(): Forum {
        return $this->forum;
    }

    /**
     * Définit le forum du rendez-vous
     *
     * @param Forum $forum Le forum
     * @return self Instance courante pour le chaînage
     */
    public function setForum(Forum $forum): self {
        $this->forum = $forum;
        return $this;
    }

    /**
     * Récupère le nom de l'entreprise
     *
     * @return string Nom de l'entreprise
     */
    public function getCompanyName(): string {
        return $this->companyName;
    }

    /**
     * Définit le nom de l'entreprise
     *
     * @param string $name Nom de l'entreprise
     * @return self Instance courante pour le chaînage
     */
    public function setCompanyName(string $name): self {
        $this->companyName = $name;
        return $this;
    }

    /**
     * Vérifie si c'est une demande de rendez-vous
     *
     * @return bool true si demande, false sinon
     */
    public function isAppointmentRequest(): bool {
        return $this->appointmentRequest;
    }

    /**
     * Définit le statut de demande de rendez-vous
     *
     * @param bool $value true si demande
     * @return self Instance courante pour le chaînage
     */
    public function setAppointmentRequest(bool $value): self {
        $this->appointmentRequest = $value;
        return $this;
    }

    /**
     * Récupère l'heure du rendez-vous
     *
     * @return ?\DateTimeInterface Heure du rendez-vous ou null si non attribué
     */
    public function getAppointmentTime(): ?\DateTimeInterface {
        return $this->appointmentTime;
    }

    /**
     * Définit l'heure du rendez-vous
     *
     * @param ?\DateTimeInterface $time Heure du rendez-vous
     * @return self Instance courante pour le chaînage
     */
    public function setAppointmentTime(?\DateTimeInterface $time): self {
        $this->appointmentTime = $time;
        return $this;
    }

    /**
     * Récupère la durée du rendez-vous
     *
     * @return int Durée en minutes
     */
    public function getDuration(): int
    {
        return $this->duration;
    }

    /**
     * Définit la durée du rendez-vous
     *
     * @param int $duration Durée en minutes
     * @return self
     */
    public function setDuration(int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }
}
