<?php

class leBonCoin_lib
	{
	public $keyword;
	public $keyword_exclude;
	public $url_lbc;
	public $data;
	public $categorie_id;
	public $page;
	public $max_page; // Maximum de pages parsée par le script
	private $tmp_data;
	private $annonce_data;
	public $debug;
	
	public function __construct()
		{
		$this->page=1;
		$this->max_page = 1000;
		$tmp_data = array();
		$this->annonce_data = array();
		}
	
	private function compare_key($key, $chaine, $all=false)
		{
		$word_nb = $word_trouve= 0;
		$keys = explode(" ", $key);
		$ok = false;
		//$chaine = str_replace(" ", " ", $chaine);
		//$chaine = str_replace(",", " ", $chaine);
		
		// ON VIRE LA VIRGULE DANS "Peugeot 205, bon etat" MAIS PAS DANS "Peugeot 205 1,6";
		$chaine = preg_replace('|([0-9]+)\,([0-9]+)|i', '$1PPVIRGPP$2', $chaine);
		$chaine = str_replace(",", " ", $chaine);
		$chaine = str_replace("PPVIRGPP", ",", $chaine);
		
		$chaine_tab = explode(' ', $chaine);
		for($a=0;$a<count($chaine_tab);$a++)
			{
			$mot_ok[vireaccent(trim(strtolower($chaine_tab[$a])))] = true;
			}
		for($a=0;$a<count($keys);$a++)
			{
			$keys[$a] = vireaccent(trim(strtolower($keys[$a])));
			//echo "Dans $chaine, il y a ".$keys[$a]." ? Position ".stripos(" ".$chaine, $keys[$a])."\n";
			if(!empty($keys[$a]))
				{
				$word_nb++;
				if($mot_ok[$keys[$a]])
					{
					$word_trouve++;
					if(!$all)
						return(true);
					}
				}
			}
		if($all && $word_nb==$word_trouve)
			$ok = true;
		elseif($all && $word_nb!=$word_trouve)
			$ok = false;
		elseif(!$all && $word_trouve > 0)
			$ok = true;
		elseif(!$all && $word_trouve == 0)
			$ok = false;
		return($ok);
		}

	public function get_url()
		{

			if(!empty($this->data["date"]))
				{
				list($date_debut, $date_fin) = explode('-', $this->data["date"]);
				// date
				if($date_debut<1960)
					$date_debut = 1960;
	
				// Fin
				if($date_fin<1960)
					$date_fin = 1960;
				}
				
		// texte ?
		if(empty($this->keyword))
			$this->keyword = '';
				
		// texte ?
		if(empty($this->keyword_exclude))
			{
			$keyword_exclude = '';
			}
		else
			{
			$keyword_exclude = ' -'.str_replace(' ', ' -', $this->keyword_exclude);
			}

		// On y va
			$url = 'https://www.leboncoin.fr/recherche/?text='.urlencode($this->keyword.''.$keyword_exclude);
			
			if(is_array($this->data))
				$url .= '&'.http_build_query($this->data);
			
			if(is_numeric($this->categorie_id))
				$url .= '&category='.$this->categorie_id;
				
			if(!empty($this->data["date"]))
				$url .= '&regdate='.$date_debut.'-'.$date_fin.'';
			 
		// Page
			if($this->page > 1)
				$url .= '&page='.$this->page;
			 
			 $this->url_lbc = $url;
			 
			 return($this->url_lbc);
		}

	public function get_annonce()
		{
			
		$url = $this->get_url();
		if($this->debug) echo  "$url\n";
		$html = curl_getpage($url);
		$ligne = count(explode("\n", $html));
		$annonces = explode('data-qa-id="aditem_container"', $html);
		if($this->debug) echo  "\tPage $page - ".count($annonces)." annonces\n";
		for($a=1;$a<count($annonces);$a++)
			{
			unset($tmp_data);
			$record_this = true;

			// Annonce ID
			preg_match('|href=\"/([a-z_]+)/([0-9]+)\.htm|i', $annonces[$a], $matches);
			$tmp_data["annonce_id"] = $matches[2];

			// Titre
			$tmp_data["titre_annonce"] = trim(strip_tags(str_ireplace('&nbsp;', ' ', decoupeStr($annonces[$a], '<span itemprop="name" data-qa-id="aditem_title"', '</span>', true))));
			$tmp_data["titre_annonce"] = vireAccent($tmp_data["titre_annonce"]);
				
			if($this->debug) echo  "\t\t\033[31mTitre : ".$tmp_data["titre_annonce"]."\033[0m\n\t\t\tKeywords : ";

			// On verifie les mots clefs
				if(!empty($this->keyword))
					{
					$key_or = explode(' or ', strtolower($this->keyword));
					$key_find = false;
					foreach($key_or as $this_key_or)
						{
						//if($this->debug) echo  "On regarde si il y a $this_key_or dans $titre_annonce\n";
						if($this->compare_key($this_key_or, $tmp_data["titre_annonce"], true))
							{
							$key_find = true;
							if($this->debug) echo  "O";
							}
						else
							if($this->debug) echo  'N';
						}
					if(!$key_find)
						{
						$record_this = false;
						}
					if(!empty($this->keyword_exclude))
						{
						if(compare_key($this->keyword_exclude, $tmp_data["titre_annonce"], false))
							{
							$record_this = false;
							$error_code = 'e';
							}
						}
					}
				
			if($this->debug) echo  "\n\t\t\tPrix : ";

			// Prix
				$prix =  trim(strip_tags(str_ireplace('&nbsp;', ' ', decoupeStr($annonces[$a], '<span itemprop="price"', '</span>', true))));
				$tmp_data["prix"] = nettoyage_de_mot($prix, '0123456789');
				if($this->debug) echo  $tmp_data["prix"]."\n";
				
			// Lieu
				$lieu = "<".decoupeStr($annonces[$a], 'itemprop="availableAtOrFrom"', '</p>', true);
				$lieu = strip_tags($lieu);
				$tmp_data["lieu"] = str_ireplace('(pro) ', '', $lieu);
				$pattern = '|([0-9]{5})|';
				preg_match($pattern, $lieu, $matches);
				$tmp_data["cp"] = $matches[1];
				if($this->debug) echo  "\t\t\tLieu : ".$tmp_data["lieu"]." (".$tmp_data["cp"].")\n";
				
			// Surface
				$titre_ss_espace = strtolower($tmp_data["titre_annonce"]);
				$titre_ss_espace = str_replace('minute', '', $titre_ss_espace);
				$titre_ss_espace = str_replace('mn', '', $titre_ss_espace);
				$titre_ss_espace = str_replace(' ', '', $titre_ss_espace);
				$pattern = '|([0-9]+)m|';
				preg_match($pattern, $titre_ss_espace, $matches);
				$tmp_data["surface"] = $matches[1];
				if($this->debug) echo  "\t\t\tSurface : ".$tmp_data["surface"]."m2\n";
				
			
			$this->annonce_data[] = $tmp_data;

			

			//if($this->debug) echo  "$annonce_id - $titre_annonce - $annee - $prix - (".$this->_debut." -> ".$this->_fin.') -> '.($record_this?'ok':'non')."\n";

			}
			
		// Page suivante ?
		// On essaye de savoir si il existe une page suivant
		
			if(stripos($html, '&amp;page='.($this->page+1))>0 && $this->page < $this->max_page)
				{
				// Il y a une page suivante
				$this->page = $this->page+1;
				$this->get_annonce();
				return($this->annonce_data);
				}
		}
	}

?>