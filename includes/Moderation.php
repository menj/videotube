<?php
final class Moderation {
    public function __construct(private PDO $db) {}
    public function apply(string $actor, string $action, int $id): void {
        if ($id<1) throw new InvalidArgumentException('Invalid target.');
        $this->db->beginTransaction();
        try {
            // Lock every administrator in the same order to protect the last active admin.
            $admins=$this->db->query("SELECT id,username,suspended FROM users WHERE role='admin' ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
            if (!array_filter($admins,fn($u)=>$u['username']===$actor && !$u['suspended'])) throw new RuntimeException('Administrator access required.');
            if (in_array($action,['suspend','restore','promote','demote'],true)) {
                $q=$this->db->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$id]);$user=$q->fetch(PDO::FETCH_ASSOC);
                if (!$user) throw new RuntimeException('User not found.');
                $active=count(array_filter($admins,fn($u)=>!$u['suspended']));
                if (in_array($action,['suspend','demote'],true) && $user['role']==='admin' && !$user['suspended'] && $active<=1) throw new RuntimeException('Keep at least one active administrator.');
                $sql=match($action) {
                    'suspend'=>'UPDATE users SET suspended=1,sessionVersion=sessionVersion+1 WHERE id=?',
                    'restore'=>'UPDATE users SET suspended=0 WHERE id=?',
                    'promote'=>"UPDATE users SET role='admin' WHERE id=?",
                    'demote'=>"UPDATE users SET role='user' WHERE id=?"
                };
                $this->db->prepare($sql)->execute([$id]);
                if ($action==='suspend') $this->db->prepare('DELETE FROM account_tokens WHERE userId=?')->execute([$id]);
            } elseif ($action==='remove_video') {
                $q=$this->db->prepare('SELECT id FROM videos WHERE id=? FOR UPDATE');$q->execute([$id]);
                if (!$q->fetchColumn()) throw new RuntimeException('Video not found.');
                // Remove content from public access while retaining files for recovery/backups.
                $this->db->prepare('DELETE FROM likes WHERE videoId=? OR commentId IN (SELECT id FROM comments WHERE videoId=?)')->execute([$id,$id]);
                $this->db->prepare('DELETE FROM dislikes WHERE videoId=? OR commentId IN (SELECT id FROM comments WHERE videoId=?)')->execute([$id,$id]);
                $this->db->prepare('DELETE FROM comments WHERE videoId=?')->execute([$id]);
                $this->db->prepare('DELETE FROM thumbnails WHERE videoId=?')->execute([$id]);
                $this->db->prepare('DELETE FROM videos WHERE id=?')->execute([$id]);
            } elseif ($action==='remove_comment') {
                $q=$this->db->prepare('SELECT id FROM comments WHERE id=? OR responseTo=? FOR UPDATE');$q->execute([$id,$id]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
                if (!$ids) throw new RuntimeException('Comment not found.');
                $marks=implode(',',array_fill(0,count($ids),'?'));
                foreach (['likes','dislikes'] as $table) $this->db->prepare("DELETE FROM $table WHERE commentId IN ($marks)")->execute($ids);
                $this->db->prepare("DELETE FROM comments WHERE id IN ($marks)")->execute($ids);
            } else throw new InvalidArgumentException('Invalid moderation action.');
            $this->db->prepare('INSERT INTO moderation_log(actor,action,target) VALUES(?,?,?)')->execute([$actor,$action,(string)$id]);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack();throw $e; }
    }
}
