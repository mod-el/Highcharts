<?php namespace Model\Highcharts;

use Model\Core\Module;

class Highcharts extends Module
{
	/**
	 * @param $list
	 * @param array $options
	 * @throws \Exception
	 */
	public function lineChart($list, array $options = [])
	{
		$options = array_merge([
			'id' => 'line-chart',
			'fields' => [],
			'label' => null,
			'label-type' => null, // supported at the moment: datetime
			'values-type' => null, // supported at the moment: price
		], $options);

		$chartOptions = [
			'chart' => [
				'zoomType' => 'x',
			],
			'title' => [
				'text' => '',
			],
			'yAxis' => [
				'title' => [
					'text' => $this->getLabel($options['fields'][0] ?? ''),
				],
			],
			'xAxis' => [
				'title' => [
					'text' => $this->getLabel($options['label'] ?: ''),
				],
			],
			'series' => [],
		];

		switch ($options['label-type']) {
			case 'datetime':
				$chartOptions['xAxis']['type'] = 'datetime';
				break;
		}

		foreach ($options['fields'] as $idx => $f) {
			if (is_numeric($idx))
				$label = $this->getLabel($f);
			else
				$label = $idx;
			$chartOptions['series'][$idx] = [
				'name' => $label,
				'data' => [],
			];
		}

		foreach ($list as $elIdx => $el) {
			foreach ($options['fields'] as $idx => $f) {
				$label = $options['label'] ? $el[$options['label']] : $elIdx;
				$v = $el[$f];
				switch ($options['label-type']) {
					case 'datetime':
						$label = 'virgdelDate.parse(\'' . $label . '\')virgdel';
						break;
				}

				$chartOptions['series'][$idx]['data'][] = [
					$label,
					$v,
				];
			}
		}
		?>
		<div id="<?= entities($options['id']) ?>"></div>
		<script>
			var chartOptions = <?=str_replace(['"virgdel', 'virgdel"'], '', json_encode($chartOptions))?>;
			<?php
			switch ($options['values-type']) {
			case 'price':
			?>
			chartOptions['yAxis']['labels'] = {
				'formatter': function () {
					return makePrice(this.value).replace('&euro;', '€');
				}
			};
			chartOptions['tooltip'] = {'pointFormat': '{series.name}: <b>{point.y:,.2f}€</b>'};
			<?php
			break;
			}
			?>
			Highcharts.chart('<?= entities($options['id']) ?>', chartOptions);
		</script>
		<?php
	}

	/**
	 * @param $list
	 * @param array $options
	 * @throws \Exception
	 */
	public function pieChart($list, array $options = [])
	{
		$options = array_merge([
			'id' => 'pie-chart',
			'field' => null,
			'label' => null,
			'label-type' => null, // supported at the moment: datetime
			'values-type' => null, // supported at the moment: price
		], $options);

		$chartOptions = [
			'chart' => [
				'type' => 'pie',
			],
			'title' => [
				'text' => '',
			],
			'plotOptions' => [
				'pie' => [
					'allowPointSelect' => false,
					'cursor' => 'pointer',
				],
			],
			'series' => [
				[
					'name' => $this->getLabel($options['field']),
					'colorByPoint' => true,
					'data' => [],
				],
			],
		];

		$numbersDirection = null;
		foreach ($list as $elIdx => $el) {
			if (is_object($el)) {
				$form = $el->getForm();
				if ($form[$options['label']])
					$label = $form[$options['label']]->getText();
				else
					$label = $el[$options['label']];
			} else {
				$label = $el[$options['label']] ?? '';
			}

			$value = $el[$options['field']];
			if (!is_numeric($value)) {
				echo 'Unsupported non-numeric value for pie chart';
				return;
			}

			if ($numbersDirection) {
				if (($numbersDirection == 1 and $value < 0) or ($numbersDirection == -1 and $value > 0)) {
					echo 'Cannot mix negative and positive numbers in a pie chart';
					return;
				}
			} else {
				$numbersDirection = $value > 0 ? 1 : -1;
			}
			if ($value < 0)
				$value = abs($value);

			$chartOptions['series'][0]['data'][] = [
				'name' => $label,
				'y' => $value,
			];
		}
		?>
		<div id="<?= entities($options['id']) ?>"></div>
		<script>
			var chartOptions = <?=json_encode($chartOptions)?>;
			<?php
			switch ($options['values-type']) {
			case 'price':
			?>
			chartOptions['tooltip'] = {'pointFormat': '{series.name}: <b>{point.y:,.2f}€</b>'};
			<?php
			break;
			}
			?>
			Highcharts.chart('<?= entities($options['id']) ?>', chartOptions);
		</script>
		<?php
	}

	/**
	 * Converts a field name in a human-readable label
	 *
	 * @param string $k
	 * @return string
	 */
	public function getLabel(string $k): string
	{
		return ucwords(str_replace(array('-', '_'), ' ', $k));
	}
}
