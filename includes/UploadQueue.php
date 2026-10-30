<?php
require_once __DIR__.'/classes/VideoUploadData.php';
require_once __DIR__.'/classes/VideoProcessor.php';
final class UploadQueue {
    public function __construct(private PDO $db) {}
    public function enqueue(VideoUploadData $data): int {
        $upload=$data->videoDataArray;
        if (!is_array($upload) || ($upload['error'] ?? -1)!==UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) throw new InvalidArgumentException('Select a valid upload.');
        $size=filesize($upload['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        if ($size<1 || $size>50000000 || !is_string($mime) || (!str_starts_with($mime,'video/') && !in_array($mime,['application/ogg','application/x-matroska'],true))) throw new InvalidArgumentException('Choose a supported video up to 50 MB.');
        if (trim($data->title)==='' || strlen($data->title)>70 || strlen($data->description)>1000 || !in_array((string)$data->privacy,['0','1'],true)) throw new InvalidArgumentException('Check video details.');
        $q=$this->db->prepare('SELECT id FROM categories WHERE id=?');$q->execute([$data->categories]);if (!$q->fetchColumn()) throw new InvalidArgumentException('Invalid category.');
        $directory=dirname(__DIR__).'/uploads/queue';
        if (!is_dir($directory) && !mkdir($directory,0750,true) && !is_dir($directory)) throw new RuntimeException('Cannot create queue storage.');
        $path=$directory.'/'.bin2hex(random_bytes(24)).'.source';
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id FROM users WHERE username=? AND suspended=0 AND emailVerifiedAt IS NOT NULL FOR UPDATE');$q->execute([$data->uploadedBy]);
            if (!$q->fetchColumn()) throw new InvalidArgumentException('Verified active account required.');
            $q=$this->db->prepare("SELECT COUNT(*) FROM upload_jobs WHERE username=? AND status IN ('pending','processing')");$q->execute([$data->uploadedBy]);
            if ((int)$q->fetchColumn()>=3) throw new InvalidArgumentException('Wait for your pending uploads to finish.');
            if (!move_uploaded_file($upload['tmp_name'],$path)) throw new RuntimeException('Cannot store upload.');
            $payload=json_encode(['title'=>$data->title,'description'=>$data->description,'category'=>$data->categories,'privacy'=>$data->privacy],JSON_THROW_ON_ERROR);
            $q=$this->db->prepare('INSERT INTO upload_jobs(username,payload,sourcePath) VALUES(?,?,?)');$q->execute([$data->uploadedBy,$payload,$path]);$id=(int)$this->db->lastInsertId();
            $this->db->commit();return $id;
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack();if (is_file($path)) unlink($path);throw $e; }
    }
    public function workOne(): bool {
        if (PHP_SAPI!=='cli') throw new RuntimeException('Workers are CLI only.');
        $lock='videotube-worker-'.substr(hash('sha256',(string)$this->db->query('SELECT DATABASE()')->fetchColumn()),0,40);
        $q=$this->db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if ((int)$q->fetchColumn()!==1) return false;
        try {
            $job=$this->db->query("SELECT * FROM upload_jobs WHERE status IN ('pending','processing') ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$job) return false;
            $this->db->prepare("UPDATE upload_jobs SET status='processing' WHERE id=?")->execute([$job['id']]);
            $source=realpath($job['sourcePath']);$root=realpath(dirname(__DIR__).'/uploads/queue');
            $validSource=$source && $root && str_starts_with($source,$root.DIRECTORY_SEPARATOR) && is_file($source);
            $existing=$this->db->prepare('SELECT id FROM videos WHERE uploadJobId=?');$existing->execute([$job['id']]);$done=(bool)$existing->fetchColumn();
            if (!$done && $validSource) {
                $q=$this->db->prepare('SELECT id FROM users WHERE username=? AND suspended=0 AND emailVerifiedAt IS NOT NULL');$q->execute([$job['username']]);
                if ($q->fetchColumn()) {
                    $p=json_decode($job['payload'],true,512,JSON_THROW_ON_ERROR);
                    $data=new VideoUploadData(['tmp_name'=>$source,'error'=>UPLOAD_ERR_OK],$p['title'],$p['description'],$p['category'],$p['privacy'],$job['username']);$data->jobId=(int)$job['id'];
                    $done=(new VideoProcessor($this->db))->upload($data,true);
                }
            }
            $this->db->prepare('UPDATE upload_jobs SET status=?,error=?,finishedAt=UTC_TIMESTAMP() WHERE id=?')->execute([$done?'complete':'failed',$done?null:'Processing failed. Check the file or contact the administrator.',$job['id']]);
            if ($validSource) unlink($source);
            return true;
        } finally { $q=$this->db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]); }
    }
}
