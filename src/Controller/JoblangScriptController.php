<?php

namespace App\Controller;

use App\Entity\Job;
use App\Entity\JoblangLine;
use App\Entity\JoblangScript;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class JoblangScriptController extends AbstractController
{
    #[Route('/add-joblang-script', name: 'add_joblang_script')]
    public function addJoblangScript(EntityManagerInterface $em): Response
    {

        $script = new JoblangScript();
        $script->setScript('// Shared script');

        $job = new Job();
        $job->setCode('J2024-001');

        $script->addJob($job);

        $job2 = new Job();
        $job2->setCode('J2024-002');

        $script->addJob($job2);

        $line = new JoblangLine();
        $line->setSource('J2024-001');
        $line->setParsed(["foo" => 'J2024-002']);

        $script->addLine($line);

        $line2 = new JoblangLine();
        $line2->setSource('J2025-001');
        $line2->setParsed(["foo" => 'J2025-002']);

        $script->addLine($line2);

        $em->persist($script);

        $em->persist($job);
        $em->persist($job2);
        $em->persist($line);
        $em->persist($line2);

        $em->flush();

//        $job = new Job();
//        $job->setCode($code);
//
//        $script = new JoblangScript();
//        $script->setScript('// JobLang script content here');
//
//        // Link both sides of the relation
//        $job->setJoblangScript($script); // this also sets $script->setJob($job) internally
//
//        $em->persist($job);
//        $em->flush();

        return new Response("Joblang script added with ID " . $script->getId());
    }

    #[Route('/list-joblang-scripts', name: 'list_joblang_scripts')]
    public function listJoblangScripts(EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(JoblangScript::class);
        $joblangScripts = $repo->findAll();

//        dump($joblangScripts);
//        die();

        $ids = array_map(fn($p) => $p->getId(), $joblangScripts);

        return new Response("Codes: " . implode(', ', $ids));
    }

    #[Route('/delete-joblang-scripts/{id}', name: 'delete_joblang_scripts')]
    public function deleteJoblangScripts(int $id, EntityManagerInterface $em): Response
    {

        $repo = $em->getRepository(JoblangScript::class);
        $joblangScript = $repo->find($id);

        $em->remove($joblangScript);
        $em->flush();

        return new Response("OK");
    }
}