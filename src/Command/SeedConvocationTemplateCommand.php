<?php

namespace App\Command;

use App\Entity\DocumentTemplate;
use App\Entity\DocumentType;
use App\Repository\DocumentTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-convocation-template',
    description: 'Create or update the default convocation document template',
)]
class SeedConvocationTemplateCommand extends Command
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

        // Find or create the CONVOCATION document type
        $documentType = $this->em->getRepository(DocumentType::class)->findOneBy(['code' => 'CONVOCATION']);
        if (!$documentType) {
            $documentType = new DocumentType();
            $documentType->setLabel('Convocation');
            $documentType->setCode('CONVOCATION');
            $this->em->persist($documentType);
            $this->em->flush();
            $io->note('DocumentType CONVOCATION created.');
        }

        $content = $this->getConvocationHtml();

        // Find existing master convocation template (no institute)
        $template = $this->repo->findOneBy([
            'documentType' => $documentType,
            'institute' => null,
        ]);

        if ($template) {
            $template->setLabel('Convocation — Modele standard');
            $template->setContent($content);
            $io->success('Master convocation template updated.');
        } else {
            $template = new DocumentTemplate();
            $template->setLabel('Convocation — Modele standard');
            $template->setDocumentType($documentType);
            $template->setContent($content);
            $template->setIsDefault(false);
            $this->em->persist($template);
            $io->success('Master convocation template created.');
        }

        $this->em->flush();

        return Command::SUCCESS;
    }

    private function getConvocationHtml(): string
    {
        // M = merge field shortcut
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
        $candAddr  = $m('candidate.address', 'Adresse');
        $candZip   = $m('candidate.zipcode', 'Code postal');
        $candCity  = $m('candidate.city', 'Ville');

        $assessment = $m('session.assessment', 'Nom du test');
        $level      = $m('session.level', 'Niveau');
        $examsTable = $m('enrollment.examsTable', 'Tableau des epreuves');

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
<p style="font-size:22px;font-weight:bold;color:#1a1a2e;margin:0;letter-spacing:2px;">CONVOCATION</p>
<p style="font-size:14px;margin:4px 0 0 0;">$assessment — $level</p>
</td></tr></table>
<p>$candCiv $candLast,</p>
<p>Nous avons le plaisir de vous confirmer votre inscription a la session d'examen suivante. Vous etes convoque(e) pour les epreuves detaillees ci-dessous :</p>
<p></p>
<p>$examsTable</p>
<p></p>
<p><strong>Consignes importantes</strong></p>
<p>Nous vous prions de bien vouloir vous presenter <strong>15 minutes avant le debut de chaque epreuve</strong>, muni(e) des documents suivants :</p>
<ul><li>La presente convocation (imprimee ou sur support numerique)</li><li>Une piece d'identite en cours de validite (carte nationale d'identite ou passeport)</li></ul>
<p><strong>Important :</strong> Tout retard pourra entrainer le refus d'acces a la salle d'examen. Aucun report ne sera possible sans accord prealable de l'institut organisateur.</p>
<p></p>
<p>Pour toute question, contactez-nous : $instEmail</p>
<p></p>
<p>Nous vous prions d'agreer, $candCiv $candLast, l'expression de nos salutations distinguees.</p>
<p></p>
<p><strong>$inst</strong></p>
HTML;
    }
}
