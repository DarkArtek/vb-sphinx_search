<?php if (!defined('VB_ENTRY')) die('Access denied.');

require_once (DIR . '/packages/vbforum/search/indexcontroller/post.php');
require_once (DIR . '/packages/vbdbsearch/indexer.php');


/**
 * Index controller for posts
 *
 */
class vBSphinxSearch_Search_IndexController_Post extends vBForum_Search_IndexController_Post
{
	/**
	 * Delete a range of posts
     * Added for compatibility with original search engine
     *
	 * @param int $start
	 * @param int $end
	 */
	public function delete_id_range($start, $end)
	{
		return false;
	}

	/**
	 * Index a thread
	 *
	 * @param int $id the thread id
	 */
	public function thread_data_change($id)
    {
		return $this->group_data_change($id);
	}

    /**
     * Called on each
     * - add / edit / delete post
     * - thread move
     * - thread soft delete
     * 
     * Don't reindex all post on each new one. That's expensive.
     * Do it only on thread soft delete. In other case - reindex
     * only first post.
     */
	public function group_data_change($id)
    {
        $thread_info = fetch_threadinfo($id);
        if (!$thread_info)
        {
            return false;
        }

        if ($thread_info['isdeleted'])
        {
            // if thread is soft deleted - update all childs
            return $this->delete_thread($id);
        }

        $first_post_info = array(
            'contentypeid'=>$this->get_contenttypeid(),
            'primaryid'=>$thread_info['firstpostid'],
        );
        $indexer = vBSphinxSearch_Core::get_instance()->get_core_indexer();
        return $indexer->index($first_post_info);
	}

	/**
     * Called on thread hard-delete. Remove all posts from index.
	 *
	 * @param int $id the thread id
	 */
	public function delete_thread($id)
    {
        global $vbulletin;
        $indexer = vBSphinxSearch_Core::get_instance()->get_core_indexer();

        $sql = "SELECT
                    " . $this->get_contenttypeid() . " AS contenttypeid,
                    post.postid AS primaryid
                FROM
                    " . TABLE_PREFIX . "post as post
            	WHERE
                    post.threadid = " . intval($id);
        $res = $vbulletin->db->query_read_slave($sql);
        while ($row = $vbulletin->db->fetch_array($res))
        {
            $indexer->delete($row['contenttypeid'], $row['primaryid']);
        }
        return true;
    }

	/**
     * We just pass this to the core indexer, which knows how to do this.
     */
	public function merge_group($oldid, $newid)
	{
        $this->delete_thread($oldid);
        $this->group_data_change($newid);
	}
}
