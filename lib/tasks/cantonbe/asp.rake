# encoding: UTF-8

namespace :cantonbe do
  namespace :asp do
    desc "generate asp json"
    task :generate => [:generate_json_from_csvs, :enrich_json]

    desc "enrich asp topf 1 and topf 2 json"
    task :enrich_json do
      topf1 = OpenBudget::Budget.from_file('data/be-asp/topf-1.json')
      topf2 = OpenBudget::Budget.from_file('data/be-asp/topf-2.json')
      budget = OpenBudget::Budget.from_file('data/be/data.json')

      (topf1.nodes + topf2.nodes).each do |node|
        budget_node = budget.get_node([node.id])
        if budget_node
          budget_node.balances.each do |key, balance|
            node.balances[key] = balance
          end
          node.short_name = budget_node.short_name
        end
      end

      topf1.save_pretty_json 'data/be-asp/topf-1.json'
      topf2.save_pretty_json 'data/be-asp/topf-2.json'
    end

    desc "creates canton bern asp topf 1 and 2 json file"
    task :generate_json_from_csvs do
      puts "start processing"

      topf1 = OpenBudget::Budget.new
      topf1.load_cantonbe_asp_csv 'source/cantonbe/asp/2013-06-28-asp-2014-massnahmen-topf-1-de.csv'

      topf2 = OpenBudget::Budget.new
      topf2.load_cantonbe_asp_csv 'source/cantonbe/asp/2013-06-28-asp-2014-massnahmen-topf-2-de.csv'

      # refator into detail class
      details = {}
      nr_to_id_path = {}
      CSV.foreach('source/cantonbe/asp/massnahmen_matching.csv', :headers => true, :header_converters => :symbol) do |row|
        next unless row[:nr].present?

        nr_to_id_path["topf#{row[:topf]}_#{row[:nr]}"] = topf1.cantonbe_names_to_ids [
          row[:overview_dir],
          row[:overview_action]
        ], :massnahme => true
      end

      massnahmen = JSON.parse File.read('source/cantonbe/asp/massnahmen.json')
      massnahmen.each do |massnahme|
        # puts "\"#{massnahme['Direktion']}\",\"#{massnahme['Massnahme']}\",#{massnahme['Nr']}"

        if massnahme['Nr'] == '10.1'
          if massnahme['Massnahme'].include? "Zusätzliche"
            id_path = nr_to_id_path["topf2_#{massnahme['Nr']}"]
          else
            id_path = nr_to_id_path["topf1_#{massnahme['Nr']}"]
          end
        else
          id_path = nr_to_id_path["topf1_#{massnahme['Nr']}"] || nr_to_id_path["topf2_#{massnahme['Nr']}"]
        end
        unless id_path
          puts "detail nr missing in matching file #{massnahme['Nr']}"
          next
        end

        node = topf1.get_node(id_path) || topf2.get_node(id_path)
        unless node
          puts "detail not found: #{id_path}"
        else
          # puts "detail found: #{id_path}"
          node.detail = true
          node.short_name = massnahme['Nr']
          if massnahme['Auswirkungen'].present? && massnahme['Auswirkungen']['Vollzeitstellen'].present?
            [2014, 2015, 2016, 2017].each do |year|
              val = massnahme['Auswirkungen']['Vollzeitstellen'][year.to_s]
              unless val == 'n.q.'
                node.add(:cuts, 'positions', year, val.to_f)
              end
            end
          end

          if massnahme['Auswirkungen'].present? && massnahme['Auswirkungen']['Finanzielle'].present?
            [2014, 2015, 2016, 2017].each do |year|
              val = massnahme['Auswirkungen']['Finanzielle'][year.to_s].to_f * (10 ** 6)
              node_val = node.balances[:cuts].accounts['budgets'][year]
              if node_val != val
                puts "---"
                puts "detected irregularity"
                puts "#{massnahme['Nr']} #{node.id} #{year}"
                puts "#{val} vs #{node_val} (detail vs overivew)"
              end
            end
          else
            puts "---"
            puts "missing Auswirkungen"
            puts "#{massnahme['Nr']} #{node.id}"
          end

          massnahme['node'] = node.as_hash_without_children
          details[node.id] = massnahme
        end
      end

      massnahmen = JSON.parse File.read('source/cantonbe/asp/massnahmen_static.json')
      massnahmen.each do |massnahme|
        id_path = massnahme['Id'].split('_')
        id_path[0] = topf1.cantonbe_directorate_id id_path[0]

        node = topf1.get_node(id_path) || topf2.get_node(id_path)
        unless node
          puts "detail not found: #{id_path}"
        else
          puts "detail found: #{id_path}"
          node.detail = true
          node.short_name = massnahme['Nr']
          if massnahme['Auswirkungen'].present? && massnahme['Auswirkungen']['Vollzeitstellen'].present?
            [2014, 2015, 2016, 2017].each do |year|
              val = massnahme['Auswirkungen']['Vollzeitstellen'][year.to_s]
              unless val == 'n.q.'
                node.add(:cuts, 'positions', year, val.to_f)
              end
            end
          end
          massnahme['node'] = node.as_hash_without_children
          details[node.id] = massnahme
        end
      end

      FileUtils.mkdir_p 'data/be-asp'

      File.open('data/be-asp/details.json', 'wb') do |file|
        file.write JSON.pretty_generate JSON.parse(details.to_json)
      end

      topf1.save_pretty_json 'data/be-asp/topf-1.json'
      topf2.save_pretty_json 'data/be-asp/topf-2.json'

      puts "done"
    end

  end
end