<?php

namespace App\Command;

use App\Entity\DocumentTemplate;
use App\Entity\DocumentType;
use App\Entity\Institute;
use App\Repository\DocumentTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-attestation-presence-template',
    description: 'Create or update the default attestation de présence template and copy it to all institutes',
)]
class SeedAttestationPresenceTemplateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentTemplateRepository $repo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. Find or create the ATTENDANCE_CERTIFICATE document type
        $documentType = $this->em->getRepository(DocumentType::class)->findOneBy(['code' => 'ATTENDANCE_CERTIFICATE']);
        if (!$documentType) {
            $documentType = new DocumentType();
            $documentType->setLabel('Attestation de présence');
            $documentType->setCode('ATTENDANCE_CERTIFICATE');
            $this->em->persist($documentType);
            $this->em->flush();
            $io->note('DocumentType ATTENDANCE_CERTIFICATE created.');
        }

        $content = $this->getAttestationHtml();

        // 2. Create or update master template (institute = null)
        $masterTemplate = $this->repo->findOneBy([
            'documentType' => $documentType,
            'institute' => null,
        ]);

        if ($masterTemplate) {
            $masterTemplate->setLabel('Attestation de présence — Modèle standard');
            $masterTemplate->setContent($content);
            $io->success('Master attestation de présence template updated.');
        } else {
            $masterTemplate = new DocumentTemplate();
            $masterTemplate->setLabel('Attestation de présence — Modèle standard');
            $masterTemplate->setDocumentType($documentType);
            $masterTemplate->setContent($content);
            $masterTemplate->setIsDefault(false);
            $this->em->persist($masterTemplate);
            $io->success('Master attestation de présence template created.');
        }

        $this->em->flush();

        // 3. Copy to all existing institutes that don't already have one
        $institutes = $this->em->getRepository(Institute::class)->findAll();
        $created = 0;

        foreach ($institutes as $institute) {
            $existing = $this->repo->findOneBy([
                'documentType' => $documentType,
                'institute' => $institute,
            ]);

            if (!$existing) {
                $tpl = new DocumentTemplate();
                $tpl->setLabel('Attestation de présence — Modèle standard');
                $tpl->setDocumentType($documentType);
                $tpl->setContent($content);
                $tpl->setIsDefault(false);
                $tpl->setInstitute($institute);
                $this->em->persist($tpl);
                $created++;
            }
        }

        $this->em->flush();

        if ($created > 0) {
            $io->success(sprintf('Template copied to %d institute(s).', $created));
        } else {
            $io->note('All institutes already have an attestation de présence template.');
        }

        return Command::SUCCESS;
    }

    private function getAttestationHtml(): string
    {
        $m = fn(string $field, string $label) =>
            '<span data-merge-field="'.$field.'" data-merge-label="'.$label.'" class="merge-field" contenteditable="false">{{ '.$label.' }}</span>';

        $inst      = $m('institute.label', "Nom de l'institut");
        $instAddr  = $m('institute.address', 'Adresse institut');
        $instZip   = $m('institute.zipcode', 'CP institut');
        $instCity  = $m('institute.city', 'Ville institut');
        $instSiren = $m('institute.siren', 'SIREN');
        $instSiret = $m('institute.siret', 'SIRET');
        $instEmail = $m('institute.email', 'Email institut');
        $instLogo  = $m('institute.logo', 'Logo institut');

        $candCiv   = $m('candidate.civility', 'Civilite');
        $candFirst = $m('candidate.firstname', 'Prenom');
        $candLast  = $m('candidate.lastname', 'Nom');
        $candBday  = $m('candidate.birthday', 'Date de naissance');
        $candAddr  = $m('candidate.address', 'Adresse');
        $candZip   = $m('candidate.zipcode', 'Code postal');
        $candCity  = $m('candidate.city', 'Ville');

        $assessment = $m('session.assessment', 'Nom du test');
        $level      = $m('session.level', 'Niveau');
        $sessStart  = $m('session.start', 'Date de debut');
        $sessEnd    = $m('session.end', 'Date de fin');
        $examsTable = $m('enrollment.examsTable', 'Tableau des epreuves');
        $regDate    = $m('enrollment.registrationDate', "Date d'inscription");

        return <<<HTML
<table style="width:100%;margin-bottom:24px;"><tr>
<td style="vertical-align:top;width:50%;">
<p style="margin:0 0 8px 0;">$instLogo</p>
<p><strong style="font-size:16px;">$inst</strong></p>
<p style="font-size:12px;color:#555;margin:2px 0;">$instAddr</p>
<p style="font-size:12px;color:#555;margin:2px 0;">$instZip $instCity</p>
<p style="font-size:12px;color:#555;margin:2px 0;">SIREN : $instSiren — SIRET : $instSiret</p>
<p style="font-size:12px;color:#555;margin:2px 0;">$instEmail</p>
</td>
<td style="vertical-align:top;width:50%;text-align:right;">
<p>$candCiv $candFirst $candLast</p>
<p style="font-size:13px;color:#555;margin:2px 0;">$candAddr</p>
<p style="font-size:13px;color:#555;margin:2px 0;">$candZip $candCity</p>
</td>
</tr></table>
<table style="width:100%;margin-bottom:24px;"><tr><td style="text-align:center;padding:14px 0 10px 0;border:2px solid #1a1a2e;">
<p style="font-size:22px;font-weight:bold;color:#1a1a2e;margin:0;letter-spacing:2px;">ATTESTATION DE PRESENCE</p>
<p style="font-size:14px;margin:4px 0 0 0;">$assessment — $level</p>
</td></tr></table>
<p>Nous soussignes, $inst, attestons que :</p>
<p></p>
<table style="width:100%;margin-bottom:16px;border-collapse:collapse;">
<tr>
<td style="padding:6px 12px;font-weight:bold;width:180px;">Nom et prenom :</td>
<td style="padding:6px 12px;">$candCiv $candFirst $candLast</td>
</tr>
<tr>
<td style="padding:6px 12px;font-weight:bold;">Date de naissance :</td>
<td style="padding:6px 12px;">$candBday</td>
</tr>
<tr>
<td style="padding:6px 12px;font-weight:bold;">Adresse :</td>
<td style="padding:6px 12px;">$candAddr, $candZip $candCity</td>
</tr>
</table>
<p>a bien ete present(e) aux epreuves suivantes dans le cadre de la session d'examen du $sessStart :</p>
<p></p>
<p>$examsTable</p>
<p></p>
<p>Cette attestation est delivree pour servir et valoir ce que de droit.</p>
<p></p>
<p style="text-align:right;">Fait a $instCity, le ____________________</p>
<p></p>
<p></p>
<p style="text-align:right;"><strong>Signature et cachet de l'institut</strong></p>
<p></p>
<p></p>
<p style="font-size:11px;color:#777;text-align:center;border-top:1px solid #ddd;padding-top:12px;">$inst — $instAddr, $instZip $instCity — SIREN : $instSiren — SIRET : $instSiret</p>
HTML;
    }
}
