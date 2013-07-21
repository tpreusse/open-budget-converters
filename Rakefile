# encoding: UTF-8

require 'pry'
require 'csv'
require 'json'
require 'nokogiri'
require 'open-uri'
require 'active_support/all'

require './lib/open_budget'

namespace :finstabe do
  namespace :bfh do
    namespace :generate_json do
      desc "creates funktionale gliederung json files (optionally pass bfs id, defaults to all)"
      task :funktionale_gliederung, [:bfs_no] do |t, args|
        args.with_defaults(:bfs_no => '*')
        files = Dir.glob("source/finstabe/bfh/funktionale_gliederung/FINSTA_NachFunktionalerGliederung_#{args.bfs_no}.csv")
        puts "start processing #{files.length} files (bfs no: #{args.bfs_no})"
        files.each do |file_path|
          budget = OpenBudget::Budget.new

          budget.from_finstabe_bfh_csv file_path
          # p nodes.nodes.to_s
          if budget.meta.present? && budget.meta[:bfs_no].present?
            File.open("data/finstabe/funktionale_gliederung/#{budget.meta[:bfs_no]}.json", 'wb') do |file|
              file.write budget.to_json
            end
            puts "#{budget.meta[:bfs_no]} done"
          else
            puts "#{file_path} meta missing"
          end
        end
      end
    end
  end
end

namespace :zurich do
  desc "creates zurich json file"
  task :generate_json_from_csv do
    puts "start processing"
    budget = OpenBudget::Budget.new

    budget.load_meta 'data/zurich/meta.json'
    budget.from_zurich_csv 'source/zurich/B13_Institution_Konzernkonto.csv'

    File.open("data/zurich/data.json", 'wb') do |file|
      file.write budget.to_json
    end
    puts "done"
  end
end

namespace :cantonbe do
  desc "creates canton bern json file"
  task :generate_json_from_csvs do
    puts "start processing"
    budget = OpenBudget::Budget.new

    budget.load_meta 'data/be/meta.json'
    budget.load_cantonbe_csv 'source/cantonbe/Kanton BE_Produkgruppen_DB IV nach DIR_2011.csv'
    budget.load_cantonbe_csv 'source/cantonbe/Kanton BE_Produkgruppen_DB IV nach DIR_2012_Werte in 1000.csv', exponent: 3, clear_comma: true

    budget.save_pretty_json 'data/be/data.json'
    puts "done"
  end

  desc "download canton bern org meta data"
  task :download_directorate_meta do
    overview = Nokogiri::HTML(open('http://www.be.ch/portal/de/behoerden/verwaltung.html'))

    directorates = []
    overview.css('.departement-index div').each do |directorate|
      h3 = directorate.css('h3 a')[0]
      next unless h3

      name = h3.content.match(/(?<name>.+)\s*\((?<acronym>.+)\)/)

      img = directorate.css('img')[0]
      director = directorate.css('ul li a')[0]

      directorates << {
        name: name[:name].strip.gsub(/\s+/, ' '),
        acronym: name[:acronym],
        website: h3[:href],
        director: {
          name: img[:alt],
          image_url: img[:src],
          website: director[:href]
        }
      }
    end

    File.open('source/cantonbe/directorate_meta.json', 'wb') do |file|
      file.write JSON.pretty_generate JSON.parse(directorates.to_json)
    end
  end

  desc "creates canton bern asp topf 1 and 2 json file"
  task :generate_json_from_asp_csvs do
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
              node.add_revenue('positions', year, val.to_f)
            end
          end
        end

        if massnahme['Auswirkungen'].present? && massnahme['Auswirkungen']['Finanzielle'].present?
          [2014, 2015, 2016, 2017].each do |year|
            val = massnahme['Auswirkungen']['Finanzielle'][year.to_s].to_f * (10 ** 6)
            node_val = node.revenue.accounts['budgets'][year]
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
              node.add_revenue('positions', year, val.to_f)
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

  desc "enrich data json"
  task :enrich_json do
    budget = OpenBudget::Budget.from_file('data/be/data.json')
    directorate_meta = JSON.parse File.read('source/cantonbe/directorate_meta.json')

    directorate_meta.each do |directorate|
      id_path = budget.cantonbe_names_to_ids [
        directorate['name']
      ]
      node = budget.get_node(id_path)
      if node
        p "found #{directorate['name']}"
        node.short_name = directorate['acronym']
      else
        p "not found #{directorate['name']} #{id_path}"
      end
    end

    budget.save_pretty_json 'data/be/data.json'
  end

  desc "enrich asp topf 1 and topf 2 json"
  task :enrich_asp_json do
    topf1 = OpenBudget::Budget.from_file('data/be-asp/topf-1.json')
    budget = OpenBudget::Budget.from_file('data/be/data.json')

    topf1.nodes.each do |node|

    end

    topf1.save_pretty_json 'data/be-asp/topf-1.json'
  end
end
