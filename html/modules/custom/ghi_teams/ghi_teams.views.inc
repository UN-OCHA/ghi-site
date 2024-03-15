<?php

/**
 * @file
 * Views plugin definitions.
 */

/**
 * Implements hook_views_data().
 */
function ghi_teams_views_data() {
  $data = [];
  $data['taxonomy_term_data']['team_member_count'] = [
    'field' => [
      'title' => t('Team member count'),
      'help' => t('Get the number of members for a team.'),
      'id' => 'team_member_count',
    ],
  ];
  $data['taxonomy_term_data']['team_section_count'] = [
    'field' => [
      'title' => t('Team section count'),
      'help' => t('Get the number of sections for a team.'),
      'id' => 'team_section_count',
    ],
  ];
  $data['taxonomy_term_data']['team_subpage_count'] = [
    'field' => [
      'title' => t('Team subpage count'),
      'help' => t('Get the number of subpages for a team.'),
      'id' => 'team_subpage_count',
    ],
  ];
  $data['taxonomy_term_data']['team_article_count'] = [
    'field' => [
      'title' => t('Team article count'),
      'help' => t('Get the number of articles for a team.'),
      'id' => 'team_article_count',
    ],
  ];
  $data['taxonomy_term_data']['team_document_count'] = [
    'field' => [
      'title' => t('Team document count'),
      'help' => t('Get the number of documents for a team.'),
      'id' => 'team_document_count',
    ],
  ];
  return $data;
}
