<?php

namespace App\Tests\Api;

use App\DataFixtures\UserFixtures;
use App\Entity\Assessment;
use App\Entity\AssessmentOwnership;
use App\Entity\Choice;
use App\Entity\Institute;
use App\Entity\InstituteMembership;
use App\Entity\Level;
use App\Entity\MCQQuestion;
use App\Entity\User;
use App\Enum\InstituteRoleEnum;
use App\Enum\MembershipStatusEnum;
use App\Enum\OwnershipTypeEnum;
use App\Enum\QuestionStatusEnum;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Outils communs aux tests de l'audit du 25/11/2026 : rôles dans un institut,
 * données pédagogiques créées à la volée et appels JSON.
 *
 * À utiliser avec ApiTestTrait. Après chaque requête du client, l'EntityManager est
 * réinitialisé : les helpers relisent toujours les entités depuis la base.
 */
trait AuditTestTrait
{
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function institute(string $label): Institute
    {
        return $this->em()->getRepository(Institute::class)->findOneBy(['label' => $label]);
    }

    private function user(string $email): User
    {
        return $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    private function level(string $ref): Level
    {
        return $this->em()->getRepository(Level::class)->findOneBy(['ref' => $ref]);
    }

    private function assessment(string $label): Assessment
    {
        return $this->em()->getRepository(Assessment::class)->findOneBy(['label' => $label]);
    }

    /**
     * Donne à l'utilisateur le rôle (et le statut) demandé dans l'institut :
     * modifie son adhésion existante, ou en crée une.
     */
    private function setMembership(
        string $email,
        string $instituteLabel,
        InstituteRoleEnum $role,
        MembershipStatusEnum $status = MembershipStatusEnum::ACTIVE,
    ): void {
        $em = $this->em();
        $institute = $this->institute($instituteLabel);
        $user = $this->user($email);

        $membership = $em->getRepository(InstituteMembership::class)->findOneBy([
            'institute' => $institute,
            'user' => $user,
        ]);
        if (!$membership) {
            $membership = new InstituteMembership();
            $membership->setUser($user);
            $membership->setInstitute($institute);
            $membership->setSince(new \DateTime());
            $em->persist($membership);
        }
        $membership->setRole($role);
        $membership->setStatus($status);
        $em->flush();
    }

    /** Test externe dont l'institut donné est propriétaire (OWNER), avec un niveau. */
    private function createAssessmentOwnedBy(string $instituteLabel, string $label, string $levelRef = 'B2'): Assessment
    {
        $em = $this->em();

        $assessment = new Assessment();
        $assessment->setLabel($label);
        $assessment->setRef(strtoupper(substr(md5($label), 0, 8)));
        $assessment->setIsInternal(false);
        $assessment->addLevel($this->level($levelRef));
        $em->persist($assessment);

        $ownership = new AssessmentOwnership();
        $ownership->setInstitute($this->institute($instituteLabel));
        $ownership->setAssessment($assessment);
        $ownership->setOwnershipType(OwnershipTypeEnum::OWNER);
        $ownership->setRelationshipDate(new \DateTime());
        $ownership->setCreator($this->user(UserFixtures::ADMIN_EMAIL));
        $em->persist($ownership);

        $em->flush();

        return $assessment;
    }

    /** QCM à deux choix (dont un correct) rattaché au test et au niveau donnés. */
    private function createMcq(
        Assessment $assessment,
        Level $level,
        string $label,
        QuestionStatusEnum $status = QuestionStatusEnum::DRAFT,
    ): MCQQuestion {
        $em = $this->em();

        $question = (new MCQQuestion())
            ->setLabel($label)
            ->setText('Énoncé de ' . $label)
            ->setAssessment($assessment)
            ->setLevel($level)
            ->setMaxPoints(1)
            ->setStatus($status);
        foreach ([['Bonne réponse', 1], ['Mauvaise réponse', 0]] as $position => [$text, $weight]) {
            $choice = (new Choice())->setText($text)->setWeight($weight)->setPosition($position);
            $question->addChoice($choice);
            $em->persist($choice);
        }
        $em->persist($question);
        $em->flush();

        return $question;
    }

    /**
     * Appel JSON ; renvoie [code HTTP, corps décodé].
     * Les méthodes PATCH utilisent application/merge-patch+json.
     */
    private function api(string $method, string $url, ?string $token = null, ?array $body = null): array
    {
        $client = static::getClient();
        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        if ($body !== null) {
            $headers['CONTENT_TYPE'] = $method === 'PATCH' ? 'application/merge-patch+json' : 'application/json';
        }

        $client->request($method, $url, [], [], $headers, $body !== null ? json_encode($body) : null);
        $response = $client->getResponse();

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
    }

    private function token(string $email): string
    {
        return $this->getJwtToken($email, UserFixtures::DEFAULT_PASSWORD);
    }
}
