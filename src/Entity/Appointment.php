<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Table(name: "appointment")]
class Appointment
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "user_id")]
    private Users $user;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(name: "forum_id", referencedColumnName: "forum_id")]
    private Forum $forum;

    #[ORM\Id]
    #[ORM\Column(name: "company_name", type: "string", length: 100)]
    private string $companyName;

    #[ORM\Column(name: "appointment_request", type: "boolean")]
    private bool $appointmentRequest;

    #[ORM\Column(name: "appointment_time", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $appointmentTime = null;

    // Getters / Setters
    public function getUser(): Users { return $this->user; }
    public function setUser(Users $user): self { $this->user = $user; return $this; }

    public function getForum(): Forum { return $this->forum; }
    public function setForum(Forum $forum): self { $this->forum = $forum; return $this; }

    public function getCompanyName(): string { return $this->companyName; }
    public function setCompanyName(string $name): self { $this->companyName = $name; return $this; }

    public function isAppointmentRequest(): bool { return $this->appointmentRequest; }
    public function setAppointmentRequest(bool $value): self { $this->appointmentRequest = $value; return $this; }

    public function getAppointmentTime(): ?\DateTimeInterface { return $this->appointmentTime; }
    public function setAppointmentTime(?\DateTimeInterface $time): self { $this->appointmentTime = $time; return $this; }
}
