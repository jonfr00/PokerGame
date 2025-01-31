# PokerGame
5 card draw poker in PHP with score and leader board
These files make up an online poker game that was developed with the help of GPT.
There is a working example here:  http://34.145.53.117/games/sfepoker/poker.php
It is running on GCP(Google Cloud) free tier with MYSQL, PHP, APACHE2
It has been though a few weeks of ironing out the bugs and improvements.
To impliment it on your platform (have only used it on Debian Linux 24.04) here are the components needed:
1. The card images ( included copy downloaded from: https://opengameart.org/content/playing-cards-vector-png ) extracted to a folder named cards (or your preference).
   Update line 511 with the path to the uploaded cards images.  This path is relative to the location of your base directory ( if it is placed in the root like /var/www/http and named cards it would be /cards
   The web execution account (www-data im my case) needs ownership ( eg: chown www-data:www-data  cards) and 755 permissions ( eg: chmod 755 cards).
2.  The poker.php file.  Edited as indicated above.
3.  The pokerscore.php ( if you are going to include the save high score option) in the same folder.  The Database, database user, table and server need to be updated in the Database connection section.
    This info can be included in a sepparate file as an include for more security.  Lines 4-7.
    A database and table will need to be in place and this script can be ran to create the needed attributes (substituting yourTable for the table name you decide to use for scores):
    CREATE TABLE `yourTable` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(120) NOT NULL default '',
  `score` int(11) NOT NULL default '0',
  `date` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=85 ; 
    
    
